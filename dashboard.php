<?php
/**
 * Dashboard API
 * Student Management System
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET') {
    switch($action) {
        case 'overview':
            getOverview();
            break;
        case 'recent_activity':
            getRecentActivity();
            break;
        case 'statistics':
            getStatistics();
            break;
        case 'enrollment_trends':
            getEnrollmentTrends();
            break;
        default:
            getOverview(); // Default to overview
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

/**
 * Get overview statistics for dashboard
 */
function getOverview() {
    $db = getDB();
    
    // Student counts
    $totalStudents = $db->query("SELECT COUNT(*) as count FROM students")->fetch()['count'];
    $activeStudents = $db->query("SELECT COUNT(*) as count FROM students WHERE status = 'Active'")->fetch()['count'];
    $inactiveStudents = $db->query("SELECT COUNT(*) as count FROM students WHERE status = 'Inactive'")->fetch()['count'];
    
    // Course counts
    $totalCourses = $db->query("SELECT COUNT(*) as count FROM courses")->fetch()['count'];
    
    // Enrollment counts
    $totalEnrollments = $db->query("SELECT COUNT(*) as count FROM enrollments")->fetch()['count'];
    $activeEnrollments = $db->query("SELECT COUNT(*) as count FROM enrollments WHERE status = 'Enrolled'")->fetch()['count'];
    $completedEnrollments = $db->query("SELECT COUNT(*) as count FROM enrollments WHERE status = 'Completed'")->fetch()['count'];
    
    // Gender distribution
    $maleStudents = $db->query("SELECT COUNT(*) as count FROM students WHERE gender = 'Male'")->fetch()['count'];
    $femaleStudents = $db->query("SELECT COUNT(*) as count FROM students WHERE gender = 'Female'")->fetch()['count'];
    
    // Recent enrollments (last 30 days)
    $recentEnrollments = $db->query("SELECT COUNT(*) as count FROM enrollments WHERE enrollment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetch()['count'];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'students' => [
                'total' => $totalStudents,
                'active' => $activeStudents,
                'inactive' => $inactiveStudents,
                'male' => $maleStudents,
                'female' => $femaleStudents
            ],
            'courses' => [
                'total' => $totalCourses
            ],
            'enrollments' => [
                'total' => $totalEnrollments,
                'active' => $activeEnrollments,
                'completed' => $completedEnrollments,
                'recent' => $recentEnrollments
            ]
        ]
    ]);
}

/**
 * Get recent activity (recent enrollments, recent students)
 */
function getRecentActivity() {
    $db = getDB();
    
    // Recent students
    $recentStudents = $db->query("SELECT id, student_id, first_name, last_name, enrollment_date, status 
        FROM students ORDER BY created_at DESC LIMIT 5")->fetchAll();
    
    // Recent enrollments with student and course info
    $recentEnrollments = $db->query("SELECT e.id, e.enrollment_date, e.status,
        s.student_id, s.first_name, s.last_name,
        c.course_code, c.course_name
        FROM enrollments e
        JOIN students s ON e.student_id = s.id
        JOIN courses c ON e.course_id = c.id
        ORDER BY e.created_at DESC LIMIT 5")->fetchAll();
    
    // Courses with most enrollments
    $popularCourses = $db->query("SELECT c.id, c.course_code, c.course_name, COUNT(e.id) as enrollment_count
        FROM courses c
        LEFT JOIN enrollments e ON c.id = e.course_id
        GROUP BY c.id
        ORDER BY enrollment_count DESC
        LIMIT 5")->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'recent_students' => $recentStudents,
            'recent_enrollments' => $recentEnrollments,
            'popular_courses' => $popularCourses
        ]
    ]);
}

/**
 * Get detailed statistics
 */
function getStatistics() {
    $db = getDB();
    
    // Students by status
    $studentsByStatus = $db->query("SELECT status, COUNT(*) as count FROM students GROUP BY status")->fetchAll();
    
    // Students by gender
    $studentsByGender = $db->query("SELECT gender, COUNT(*) as count FROM students WHERE gender IS NOT NULL GROUP BY gender")->fetchAll();
    
    // Enrollments by status
    $enrollmentsByStatus = $db->query("SELECT status, COUNT(*) as count FROM enrollments GROUP BY status")->fetchAll();
    
    // Courses by department
    $coursesByDepartment = $db->query("SELECT department, COUNT(*) as count FROM courses WHERE department IS NOT NULL GROUP BY department")->fetchAll();
    
    // Average students per course
    $avgStudentsPerCourse = $db->query("SELECT AVG(enrollment_count) as avg_count FROM (
        SELECT course_id, COUNT(*) as enrollment_count FROM enrollments GROUP BY course_id
    ) as course_counts")->fetch()['avg_count'];
    
    // Students enrolled per course
    $studentsPerCourse = $db->query("SELECT c.course_code, c.course_name, COUNT(e.id) as student_count
        FROM courses c
        LEFT JOIN enrollments e ON c.id = e.course_id
        GROUP BY c.id
        ORDER BY student_count DESC")->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'students_by_status' => $studentsByStatus,
            'students_by_gender' => $studentsByGender,
            'enrollments_by_status' => $enrollmentsByStatus,
            'courses_by_department' => $coursesByDepartment,
            'avg_students_per_course' => round($avgStudentsPerCourse ?? 0, 1),
            'students_per_course' => $studentsPerCourse
        ]
    ]);
}

/**
 * Get enrollment trends (monthly data)
 */
function getEnrollmentTrends() {
    $db = getDB();
    
    // Monthly enrollments for current year
    $monthlyEnrollments = $db->query("SELECT 
        MONTH(enrollment_date) as month,
        COUNT(*) as count
        FROM enrollments
        WHERE YEAR(enrollment_date) = YEAR(CURDATE())
        GROUP BY MONTH(enrollment_date)
        ORDER BY month")->fetchAll();
    
    // Monthly student registrations for current year
    $monthlyRegistrations = $db->query("SELECT 
        MONTH(created_at) as month,
        COUNT(*) as count
        FROM students
        WHERE YEAR(created_at) = YEAR(CURDATE())
        GROUP BY MONTH(created_at)
        ORDER BY month")->fetchAll();
    
    // Yearly comparison
    $yearlyEnrollments = $db->query("SELECT 
        YEAR(enrollment_date) as year,
        COUNT(*) as count
        FROM enrollments
        GROUP BY YEAR(enrollment_date)
        ORDER BY year DESC
        LIMIT 5")->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'monthly_enrollments' => $monthlyEnrollments,
            'monthly_registrations' => $monthlyRegistrations,
            'yearly_enrollments' => $yearlyEnrollments
        ]
    ]);
}
