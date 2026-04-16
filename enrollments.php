<?php
/**
 * Enrollments API
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
            getEnrollments();
        } elseif ($action === 'by_student' && isset($_GET['student_id'])) {
            getStudentEnrollments($_GET['student_id']);
        } elseif ($action === 'by_course' && isset($_GET['course_id'])) {
            getCourseEnrollments($_GET['course_id']);
        }
        break;
    case 'POST':
        addEnrollment();
        break;
    case 'PUT':
        updateEnrollment();
        break;
    case 'DELETE':
        deleteEnrollment();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

function getEnrollments() {
    $db = getDB();
    $stmt = $db->query("SELECT e.*, s.student_id as stu_id, s.first_name, s.last_name, 
        c.course_code, c.course_name 
        FROM enrollments e 
        JOIN students s ON e.student_id = s.id 
        JOIN courses c ON e.course_id = c.id 
        ORDER BY e.enrollment_date DESC");
    $enrollments = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $enrollments]);
}

function getStudentEnrollments($studentId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT e.*, c.course_code, c.course_name, c.credits 
        FROM enrollments e 
        JOIN courses c ON e.course_id = c.id 
        WHERE e.student_id = ? 
        ORDER BY c.course_name");
    $stmt->execute([$studentId]);
    $enrollments = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $enrollments]);
}

function getCourseEnrollments($courseId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT e.*, s.student_id, s.first_name, s.last_name, s.email 
        FROM enrollments e 
        JOIN students s ON e.student_id = s.id 
        WHERE e.course_id = ? 
        ORDER BY s.last_name, s.first_name");
    $stmt->execute([$courseId]);
    $enrollments = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $enrollments]);
}

function addEnrollment() {
    $db = getDB();
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check if enrollment already exists
    $checkStmt = $db->prepare("SELECT id FROM enrollments WHERE student_id = ? AND course_id = ?");
    $checkStmt->execute([$data['student_id'], $data['course_id']]);
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Student already enrolled in this course']);
        return;
    }
    
    $stmt = $db->prepare("INSERT INTO enrollments (
        student_id, course_id, enrollment_date, status
    ) VALUES (?, ?, ?, ?)");
    
    $result = $stmt->execute([
        $data['student_id'],
        $data['course_id'],
        $data['enrollment_date'] ?? date('Y-m-d'),
        $data['status'] ?? 'Enrolled'
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Enrollment added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add enrollment']);
    }
}

function updateEnrollment() {
    $db = getDB();
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $_GET['id'] ?? 0;
    
    $stmt = $db->prepare("UPDATE enrollments SET 
        status = ?, grade = ?
        WHERE id = ?");
    
    $result = $stmt->execute([
        $data['status'] ?? 'Enrolled',
        $data['grade'] ?? null,
        $id
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Enrollment updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update enrollment']);
    }
}

function deleteEnrollment() {
    $db = getDB();
    $id = $_GET['id'] ?? 0;
    
    $stmt = $db->prepare("DELETE FROM enrollments WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Enrollment deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete enrollment']);
    }
}