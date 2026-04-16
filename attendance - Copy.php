<?php
/**
 * Attendance API
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
            getAttendanceRecords();
        } elseif ($action === 'by_student' && isset($_GET['student_id'])) {
            getStudentAttendance($_GET['student_id']);
        } elseif ($action === 'by_course' && isset($_GET['course_id'])) {
            getCourseAttendance($_GET['course_id']);
        } elseif ($action === 'by_date' && isset($_GET['date'])) {
            getAttendanceByDate($_GET['date']);
        } elseif ($action === 'stats' && isset($_GET['student_id'])) {
            getStudentAttendanceStats($_GET['student_id']);
        } elseif ($action === 'report') {
            getAttendanceReport();
        }
        break;
    case 'POST':
        markAttendance();
        break;
    case 'PUT':
        updateAttendance();
        break;
    case 'DELETE':
        deleteAttendance();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

/**
 * Get all attendance records
 */
function getAttendanceRecords() {
    $db = getDB();
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    $stmt = $db->query("SELECT a.*, s.student_id, s.first_name, s.last_name,
        c.course_code, c.course_name
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        JOIN courses c ON a.course_id = c.id
        ORDER BY a.date DESC, s.last_name
        LIMIT $limit OFFSET $offset");
    $records = $stmt->fetchAll();
    
    // Get total count
    $total = $db->query("SELECT COUNT(*) as count FROM attendance")->fetch()['count'];
    
    echo json_encode([
        'success' => true,
        'data' => $records,
        'pagination' => [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ]
    ]);
}

/**
 * Get attendance for a specific student
 */
function getStudentAttendance($studentId) {
    $db = getDB();
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-t');
    
    $stmt = $db->prepare("SELECT a.*, c.course_code, c.course_name
        FROM attendance a
        JOIN courses c ON a.course_id = c.id
        WHERE a.student_id = ? AND a.date BETWEEN ? AND ?
        ORDER BY a.date DESC, c.course_name");
    $stmt->execute([$studentId, $startDate, $endDate]);
    $records = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $records]);
}

/**
 * Get attendance for a specific course
 */
function getCourseAttendance($courseId) {
    $db = getDB();
    $date = $_GET['date'] ?? date('Y-m-d');
    
    $stmt = $db->prepare("SELECT a.*, s.student_id, s.first_name, s.last_name
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        WHERE a.course_id = ? AND a.date = ?
        ORDER BY s.last_name, s.first_name");
    $stmt->execute([$courseId, $date]);
    $records = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $records]);
}

/**
 * Get attendance records by specific date
 */
function getAttendanceByDate($date) {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT a.*, s.student_id, s.first_name, s.last_name,
        c.course_code, c.course_name
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        JOIN courses c ON a.course_id = c.id
        WHERE a.date = ?
        ORDER BY c.course_name, s.last_name");
    $stmt->execute([$date]);
    $records = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $records]);
}

/**
 * Get attendance statistics for a student
 */
function getStudentAttendanceStats($studentId) {
    $db = getDB();
    
    $total = $db->prepare("SELECT COUNT(*) as count FROM attendance WHERE student_id = ?");
    $total->execute([$studentId]);
    $totalCount = $total->fetch()['count'];
    
    $present = $db->prepare("SELECT COUNT(*) as count FROM attendance WHERE student_id = ? AND status = 'Present'");
    $present->execute([$studentId]);
    $presentCount = $present->fetch()['count'];
    
    $absent = $db->prepare("SELECT COUNT(*) as count FROM attendance WHERE student_id = ? AND status = 'Absent'");
    $absent->execute([$studentId]);
    $absentCount = $absent->fetch()['count'];
    
    $late = $db->prepare("SELECT COUNT(*) as count FROM attendance WHERE student_id = ? AND status = 'Late'");
    $late->execute([$studentId]);
    $lateCount = $late->fetch()['count'];
    
    $excused = $db->prepare("SELECT COUNT(*) as count FROM attendance WHERE student_id = ? AND status = 'Excused'");
    $excused->execute([$studentId]);
    $excusedCount = $excused->fetch()['count'];
    
    $attendanceRate = $totalCount > 0 ? round(($presentCount + $lateCount) / $totalCount * 100, 1) : 0;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total' => $totalCount,
            'present' => $presentCount,
            'absent' => $absentCount,
            'late' => $lateCount,
            'excused' => $excusedCount,
            'attendance_rate' => $attendanceRate
        ]
    ]);
}

/**
 * Get attendance report (summary by course or student)
 */
function getAttendanceReport() {
    $db = getDB();
    $reportType = $_GET['type'] ?? 'course'; // course or student
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-t');
    
    if ($reportType === 'course') {
        $stmt = $db->prepare("SELECT c.id, c.course_code, c.course_name,
            COUNT(a.id) as total_records,
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late,
            SUM(CASE WHEN a.status = 'Excused' THEN 1 ELSE 0 END) as excused
            FROM courses c
            LEFT JOIN attendance a ON c.id = a.course_id AND a.date BETWEEN ? AND ?
            GROUP BY c.id
            ORDER BY c.course_name");
        $stmt->execute([$startDate, $endDate]);
        $data = $stmt->fetchAll();
    } else {
        $stmt = $db->prepare("SELECT s.id, s.student_id, s.first_name, s.last_name,
            COUNT(a.id) as total_records,
            SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late,
            SUM(CASE WHEN a.status = 'Excused' THEN 1 ELSE 0 END) as excused
            FROM students s
            LEFT JOIN attendance a ON s.id = a.student_id AND a.date BETWEEN ? AND ?
            GROUP BY s.id
            ORDER BY s.last_name, s.first_name");
        $stmt->execute([$startDate, $endDate]);
        $data = $stmt->fetchAll();
    }
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'filters' => [
            'type' => $reportType,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]
    ]);
}

/**
 * Mark attendance (single or bulk)
 */
function markAttendance() {
    $db = getDB();
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check if bulk attendance
    if (isset($data['records']) && is_array($data['records'])) {
        $results = [];
        foreach ($data['records'] as $record) {
            $result = markSingleAttendance($db, $record);
            $results[] = $result;
        }
        echo json_encode([
            'success' => true,
            'message' => 'Attendance marked successfully',
            'results' => $results
        ]);
    } else {
        $result = markSingleAttendance($db, $data);
        if ($result['success']) {
            echo json_encode(['success' => true, 'message' => 'Attendance marked successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => $result['message']]);
        }
    }
}

/**
 * Mark single attendance record
 */
function markSingleAttendance($db, $record) {
    // Check if record already exists
    $checkStmt = $db->prepare("SELECT id FROM attendance WHERE student_id = ? AND course_id = ? AND date = ?");
    $checkStmt->execute([$record['student_id'], $record['course_id'], $record['date'] ?? date('Y-m-d')]);
    $existing = $checkStmt->fetch();
    
    if ($existing) {
        // Update existing record
        $stmt = $db->prepare("UPDATE attendance SET status = ?, remarks = ? WHERE id = ?");
        $stmt->execute([
            $record['status'] ?? 'Present',
            $record['remarks'] ?? null,
            $existing['id']
        ]);
        return ['success' => true, 'action' => 'updated', 'id' => $existing['id']];
    } else {
        // Insert new record
        $stmt = $db->prepare("INSERT INTO attendance (student_id, course_id, date, status, remarks) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $record['student_id'],
            $record['course_id'],
            $record['date'] ?? date('Y-m-d'),
            $record['status'] ?? 'Present',
            $record['remarks'] ?? null
        ]);
        return ['success' => true, 'action' => 'created', 'id' => $db->lastInsertId()];
    }
}

/**
 * Update attendance record
 */
function updateAttendance() {
    $db = getDB();
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $_GET['id'] ?? 0;
    
    $stmt = $db->prepare("UPDATE attendance SET status = ?, remarks = ? WHERE id = ?");
    $result = $stmt->execute([
        $data['status'] ?? 'Present',
        $data['remarks'] ?? null,
        $id
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Attendance updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update attendance']);
    }
}

/**
 * Delete attendance record
 */
function deleteAttendance() {
    $db = getDB();
    $id = $_GET['id'] ?? 0;
    
    $stmt = $db->prepare("DELETE FROM attendance WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Attendance record deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete attendance record']);
    }
}
