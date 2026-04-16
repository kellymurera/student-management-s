<?php
/**
 * Grades API
 * Student Management System
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch($method) {
    case 'GET':
        if ($action === 'list') {
            getGrades();
        } elseif ($action === 'by_student' && isset($_GET['student_id'])) {
            getStudentGrades($_GET['student_id']);
        } elseif ($action === 'by_course' && isset($_GET['course_id'])) {
            getCourseGrades($_GET['course_id']);
        } elseif ($action === 'transcript' && isset($_GET['student_id'])) {
            getStudentTranscript($_GET['student_id']);
        } elseif ($action === 'stats' && isset($_GET['student_id'])) {
            getStudentGradeStats($_GET['student_id']);
        } elseif ($action === 'report') {
            getGradeReport();
        }
        break;
    case 'POST':
        addGrade();
        break;
    case 'PUT':
        updateGrade();
        break;
    case 'DELETE':
        deleteGrade();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

/**
 * Get all grades with pagination
 */
function getGrades() {
    $db = getDB();
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    $stmt = $db->query("SELECT g.*, s.student_id, s.first_name, s.last_name,
        c.course_code, c.course_name, c.credits
        FROM grades g
        JOIN students s ON g.student_id = s.id
        JOIN courses c ON g.course_id = c.id
        ORDER BY g.created_at DESC
        LIMIT $limit OFFSET $offset");
    $grades = $stmt->fetchAll();
    
    // Get total count
    $total = $db->query("SELECT COUNT(*) as count FROM grades")->fetch()['count'];
    
    echo json_encode([
        'success' => true,
        'data' => $grades,
        'pagination' => [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ]
    ]);
}

/**
 * Get grades for a specific student
 */
function getStudentGrades($studentId) {
    $db = getDB();
    $semester = $_GET['semester'] ?? null;
    $academicYear = $_GET['academic_year'] ?? null;
    
    $query = "SELECT g.*, c.course_code, c.course_name, c.credits, c.department
        FROM grades g
        JOIN courses c ON g.course_id = c.id
        WHERE g.student_id = ?";
    $params = [$studentId];
    
    if ($semester) {
        $query .= " AND g.semester = ?";
        $params[] = $semester;
    }
    
    if ($academicYear) {
        $query .= " AND g.academic_year = ?";
        $params[] = $academicYear;
    }
    
    $query .= " ORDER BY g.semester, g.academic_year, c.course_name";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $grades = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $grades]);
}

/**
 * Get grades for a specific course
 */
function getCourseGrades($courseId) {
    $db = getDB();
    $semester = $_GET['semester'] ?? null;
    
    $query = "SELECT g.*, s.student_id, s.first_name, s.last_name
        FROM grades g
        JOIN students s ON g.student_id = s.id
        WHERE g.course_id = ?";
    $params = [$courseId];
    
    if ($semester) {
        $query .= " AND g.semester = ?";
        $params[] = $semester;
    }
    
    $query .= " ORDER BY s.last_name, s.first_name";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $grades = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $grades]);
}

/**
 * Get student transcript (summary of all grades)
 */
function getStudentTranscript($studentId) {
    $db = getDB();
    
    // Get student info
    $studentStmt = $db->prepare("SELECT * FROM students WHERE id = ?");
    $studentStmt->execute([$studentId]);
    $student = $studentStmt->fetch();
    
    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        return;
    }
    
    // Get all grades grouped by semester/year
    $gradesStmt = $db->prepare("SELECT g.*, c.course_code, c.course_name, c.credits, c.department,
        e.status as enrollment_status
        FROM grades g
        JOIN courses c ON g.course_id = c.id
        LEFT JOIN enrollments e ON g.student_id = e.student_id AND g.course_id = e.course_id
        WHERE g.student_id = ?
        ORDER BY g.semester, g.academic_year, c.course_name");
    $gradesStmt->execute([$studentId]);
    $grades = $gradesStmt->fetchAll();
    
    // Calculate GPA
    $gpaData = calculateGPA($db, $studentId);
    
    // Group by semester
    $transcript = [];
    foreach ($grades as $grade) {
        $key = $grade['semester'] . '-' . $grade['academic_year'];
        if (!isset($transcript[$key])) {
            $transcript[$key] = [
                'semester' => $grade['semester'],
                'academic_year' => $grade['academic_year'],
                'courses' => []
            ];
        }
        $transcript[$key]['courses'][] = $grade;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'student' => $student,
            'transcript' => array_values($transcript),
            'gpa' => $gpaData
        ]
    ]);
}

/**
 * Calculate GPA for a student
 */
function calculateGPA($db, $studentId) {
    // Get all grades with course credits
    $stmt = $db->prepare("SELECT g.score, c.credits
        FROM grades g
        JOIN courses c ON g.course_id = c.id
        WHERE g.student_id = ?");
    $stmt->execute([$studentId]);
    $grades = $stmt->fetchAll();
    
    if (empty($grades)) {
        return [
            'gpa' => 0,
            'total_credits' => 0,
            'grade_points' => 0
        ];
    }
    
    $totalPoints = 0;
    $totalCredits = 0;
    
    foreach ($grades as $grade) {
        $points = scoreToGradePoints($grade['score']);
        $totalPoints += $points * $grade['credits'];
        $totalCredits += $grade['credits'];
    }
    
    $gpa = $totalCredits > 0 ? round($totalPoints / $totalCredits, 2) : 0;
    
    return [
        'gpa' => $gpa,
        'total_credits' => $totalCredits,
        'grade_points' => round($totalPoints, 2),
        'courses_count' => count($grades)
    ];
}

/**
 * Convert numeric score to grade points
 */
function scoreToGradePoints($score) {
    if ($score >= 90) return 4.0;
    if ($score >= 85) return 3.7;
    if ($score >= 80) return 3.3;
    if ($score >= 75) return 3.0;
    if ($score >= 70) return 2.7;
    if ($score >= 65) return 2.3;
    if ($score >= 60) return 2.0;
    if ($score >= 55) return 1.7;
    if ($score >= 50) return 1.3;
    if ($score >= 40) return 1.0;
    return 0.0;
}

/**
 * Get letter grade from score
 */
function getLetterGrade($score) {
    if ($score >= 90) return 'A';
    if ($score >= 85) return 'A-';
    if ($score >= 80) return 'B+';
    if ($score >= 75) return 'B';
    if ($score >= 70) return 'B-';
    if ($score >= 65) return 'C+';
    if ($score >= 60) return 'C';
    if ($score >= 55) return 'C-';
    if ($score >= 50) return 'D';
    if ($score >= 40) return 'D-';
    return 'F';
}

/**
 * Get grade statistics for a student
 */
function getStudentGradeStats($studentId) {
    $db = getDB();
    
    // Overall stats
    $totalGrades = $db->prepare("SELECT COUNT(*) as count FROM grades WHERE student_id = ?");
    $totalGrades->execute([$studentId]);
    $totalCount = $totalGrades->fetch()['count'];
    
    $avgScore = $db->prepare("SELECT AVG(score) as avg FROM grades WHERE student_id = ?");
    $avgScore->execute([$studentId]);
    $average = $avgScore->fetch()['avg'];
    
    $highestScore = $db->prepare("SELECT MAX(score) as max FROM grades WHERE student_id = ?");
    $highestScore->execute([$studentId]);
    $highest = $highestScore->fetch()['max'];
    
    $lowestScore = $db->prepare("SELECT MIN(score) as min FROM grades WHERE student_id = ?");
    $lowestScore->execute([$studentId]);
    $lowest = $lowestScore->fetch()['min'];
    
    // Stats by course
    $courseStats = $db->prepare("SELECT c.course_name, AVG(g.score) as avg_score, COUNT(g.id) as assignments
        FROM grades g
        JOIN courses c ON g.course_id = c.id
        WHERE g.student_id = ?
        GROUP BY g.course_id
        ORDER BY avg_score DESC");
    $courseStats->execute([$studentId]);
    $courses = $courseStats->fetchAll();
    
    // Grade distribution
    $gradeDistribution = $db->prepare("SELECT 
        SUM(CASE WHEN score >= 90 THEN 1 ELSE 0 END) as A,
        SUM(CASE WHEN score >= 80 AND score < 90 THEN 1 ELSE 0 END) as B,
        SUM(CASE WHEN score >= 70 AND score < 80 THEN 1 ELSE 0 END) as C,
        SUM(CASE WHEN score >= 60 AND score < 70 THEN 1 ELSE 0 END) as D,
        SUM(CASE WHEN score < 60 THEN 1 ELSE 0 END) as F
        FROM grades WHERE student_id = ?");
    $gradeDistribution->execute([$studentId]);
    $distribution = $gradeDistribution->fetch();
    
    // GPA
    $gpa = calculateGPA($db, $studentId);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total_assignments' => $totalCount,
            'average_score' => round($average ?? 0, 1),
            'highest_score' => $highest,
            'lowest_score' => $lowest,
            'gpa' => $gpa,
            'grade_distribution' => $distribution,
            'course_stats' => $courses
        ]
    ]);
}

/**
 * Get grade report (course averages, student rankings)
 */
function getGradeReport() {
    $db = getDB();
    $courseId = $_GET['course_id'] ?? null;
    $semester = $_GET['semester'] ?? null;
    
    if ($courseId) {
        // Course grade report
        $stmt = $db->prepare("SELECT g.*, s.student_id, s.first_name, s.last_name
            FROM grades g
            JOIN students s ON g.student_id = s.id
            WHERE g.course_id = ?" . ($semester ? " AND g.semester = ?" : "") .
            " ORDER BY g.score DESC");
        $params = $semester ? [$courseId, $semester] : [$courseId];
        $stmt->execute($params);
        $grades = $stmt->fetchAll();
        
        // Course statistics
        $statsStmt = $db->prepare("SELECT 
            AVG(score) as average,
            MAX(score) as highest,
            MIN(score) as lowest,
            COUNT(DISTINCT student_id) as students_count
            FROM grades WHERE course_id = ?" . ($semester ? " AND semester = ?" : ""));
        $statsStmt->execute($params);
        $stats = $statsStmt->fetch();
        
        echo json_encode([
            'success' => true,
            'data' => [
                'grades' => $grades,
                'statistics' => $stats
            ]
        ]);
    } else {
        // Overall student rankings
        $stmt = $db->query("SELECT s.id, s.student_id, s.first_name, s.last_name,
            AVG(g.score) as average_score,
            COUNT(g.id) as total_grades
            FROM students s
            LEFT JOIN grades g ON s.id = g.student_id
            GROUP BY s.id
            HAVING total_grades > 0
            ORDER BY average_score DESC");
        $rankings = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'data' => $rankings
        ]);
    }
}

/**
 * Add a new grade
 */
function addGrade() {
    $db = getDB();
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($data['student_id']) || empty($data['course_id'])) {
        echo json_encode(['success' => false, 'message' => 'Student ID and Course ID are required']);
        return;
    }
    
    $stmt = $db->prepare("INSERT INTO grades (
        student_id, course_id, assignment_name, score, max_score,
        semester, academic_year
    ) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    $result = $stmt->execute([
        $data['student_id'],
        $data['course_id'],
        $data['assignment_name'] ?? 'Assignment',
        $data['score'] ?? 0,
        $data['max_score'] ?? 100,
        $data['semester'] ?? 'Fall 2024',
        $data['academic_year'] ?? date('Y')
    ]);
    
    if ($result) {
        $gradeId = $db->lastInsertId();
        // Calculate letter grade
        $score = $data['score'] ?? 0;
        $letterGrade = getLetterGrade($score);
        
        echo json_encode([
            'success' => true,
            'message' => 'Grade added successfully',
            'data' => [
                'id' => $gradeId,
                'score' => $score,
                'letter_grade' => $letterGrade,
                'grade_points' => scoreToGradePoints($score)
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add grade']);
    }
}

/**
 * Update an existing grade
 */
function updateGrade() {
    $db = getDB();
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $_GET['id'] ?? 0;
    
    $stmt = $db->prepare("UPDATE grades SET 
        assignment_name = ?, score = ?, max_score = ?,
        semester = ?, academic_year = ?
        WHERE id = ?");
    
    $result = $stmt->execute([
        $data['assignment_name'] ?? 'Assignment',
        $data['score'] ?? 0,
        $data['max_score'] ?? 100,
        $data['semester'] ?? 'Fall 2024',
        $data['academic_year'] ?? date('Y'),
        $id
    ]);
    
    if ($result) {
        $score = $data['score'] ?? 0;
        $letterGrade = getLetterGrade($score);
        
        echo json_encode([
            'success' => true,
            'message' => 'Grade updated successfully',
            'data' => [
                'score' => $score,
                'letter_grade' => $letterGrade,
                'grade_points' => scoreToGradePoints($score)
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update grade']);
    }
}

/**
 * Delete a grade
 */
function deleteGrade() {
    $db = getDB();
    $id = $_GET['id'] ?? 0;
    
    $stmt = $db->prepare("DELETE FROM grades WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Grade deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete grade']);
    }
}
