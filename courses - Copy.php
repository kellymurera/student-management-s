<?php
/**
 * Courses API
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
            getCourses();
        } elseif ($action === 'single' && isset($_GET['id'])) {
            getCourse($_GET['id']);
        } elseif ($action === 'search' && isset($_GET['q'])) {
            searchCourses($_GET['q']);
        } elseif ($action === 'stats') {
            getCourseStats();
        }
        break;
    case 'POST':
        addCourse();
        break;
    case 'PUT':
        updateCourse();
        break;
    case 'DELETE':
        deleteCourse();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

function getCourses() {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM courses ORDER BY course_name ASC");
    $courses = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $courses]);
}

function getCourse($id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$id]);
    $course = $stmt->fetch();
    if ($course) {
        echo json_encode(['success' => true, 'data' => $course]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Course not found']);
    }
}

function searchCourses($query) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM courses WHERE 
        course_code LIKE ? OR 
        course_name LIKE ? OR 
        department LIKE ? 
        ORDER BY course_name");
    $searchTerm = "%$query%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $courses = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $courses]);
}

function getCourseStats() {
    $db = getDB();
    
    $total = $db->query("SELECT COUNT(*) as count FROM courses")->fetch()['count'];
    $totalStudents = $db->query("SELECT COUNT(*) as count FROM enrollments")->fetch()['count'];
    $avgEnrollment = $total > 0 ? round($totalStudents / $total, 1) : 0;
    
    echo json_encode([
        'success' => true, 
        'data' => [
            'total' => $total,
            'totalStudents' => $totalStudents,
            'avgEnrollment' => $avgEnrollment
        ]
    ]);
}

function addCourse() {
    $db = getDB();
    $data = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $db->prepare("INSERT INTO courses (
        course_code, course_name, description, credits, 
        department, instructor, max_students
    ) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    $result = $stmt->execute([
        $data['course_code'] ?? '',
        $data['course_name'] ?? '',
        $data['description'] ?? '',
        $data['credits'] ?? 3,
        $data['department'] ?? '',
        $data['instructor'] ?? '',
        $data['max_students'] ?? 30
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Course added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add course']);
    }
}

function updateCourse() {
    $db = getDB();
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $_GET['id'] ?? 0;
    
    $stmt = $db->prepare("UPDATE courses SET 
        course_code = ?, course_name = ?, description = ?, credits = ?,
        department = ?, instructor = ?, max_students = ?
        WHERE id = ?");
    
    $result = $stmt->execute([
        $data['course_code'] ?? '',
        $data['course_name'] ?? '',
        $data['description'] ?? '',
        $data['credits'] ?? 3,
        $data['department'] ?? '',
        $data['instructor'] ?? '',
        $data['max_students'] ?? 30,
        $id
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Course updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update course']);
    }
}

function deleteCourse() {
    $db = getDB();
    $id = $_GET['id'] ?? 0;
    
    $stmt = $db->prepare("DELETE FROM courses WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Course deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete course']);
    }
}