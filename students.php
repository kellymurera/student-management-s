<?php
/**
 * Students API
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
            getStudents();
        } elseif ($action === 'single' && isset($_GET['id'])) {
            getStudent($_GET['id']);
        } elseif ($action === 'search' && isset($_GET['q'])) {
            searchStudents($_GET['q']);
        } elseif ($action === 'stats') {
            getStudentStats();
        }
        break;
    case 'POST':
        addStudent();
        break;
    case 'PUT':
        updateStudent();
        break;
    case 'DELETE':
        deleteStudent();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

function getStudents() {
    $db = getDB();
    $stmt = $db->query("SELECT * FROM students ORDER BY created_at DESC");
    $students = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $students]);
}

function getStudent($id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$id]);
    $student = $stmt->fetch();
    if ($student) {
        echo json_encode(['success' => true, 'data' => $student]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
    }
}

function searchStudents($query) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM students WHERE 
        student_id LIKE ? OR 
        first_name LIKE ? OR 
        last_name LIKE ? OR 
        email LIKE ? 
        ORDER BY last_name, first_name");
    $searchTerm = "%$query%";
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $students = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $students]);
}

function getStudentStats() {
    $db = getDB();
    
    $total = $db->query("SELECT COUNT(*) as count FROM students")->fetch()['count'];
    $active = $db->query("SELECT COUNT(*) as count FROM students WHERE status = 'Active'")->fetch()['count'];
    $inactive = $db->query("SELECT COUNT(*) as count FROM students WHERE status = 'Inactive'")->fetch()['count'];
    
    echo json_encode([
        'success' => true, 
        'data' => [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive
        ]
    ]);
}

function addStudent() {
    $db = getDB();
    $data = json_decode(file_get_contents('php://input'), true);
    
    $stmt = $db->prepare("INSERT INTO students (
        student_id, first_name, last_name, email, phone, 
        date_of_birth, gender, address, city, country,
        parent_name, parent_phone, enrollment_date, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $result = $stmt->execute([
        $data['student_id'] ?? '',
        $data['first_name'] ?? '',
        $data['last_name'] ?? '',
        $data['email'] ?? '',
        $data['phone'] ?? '',
        $data['date_of_birth'] ?? null,
        $data['gender'] ?? '',
        $data['address'] ?? '',
        $data['city'] ?? '',
        $data['country'] ?? '',
        $data['parent_name'] ?? '',
        $data['parent_phone'] ?? '',
        $data['enrollment_date'] ?? date('Y-m-d'),
        $data['status'] ?? 'Active'
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Student added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add student']);
    }
}

function updateStudent() {
    $db = getDB();
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $_GET['id'] ?? 0;
    
    $stmt = $db->prepare("UPDATE students SET 
        student_id = ?, first_name = ?, last_name = ?, email = ?, phone = ?,
        date_of_birth = ?, gender = ?, address = ?, city = ?, country = ?,
        parent_name = ?, parent_phone = ?, status = ?
        WHERE id = ?");
    
    $result = $stmt->execute([
        $data['student_id'] ?? '',
        $data['first_name'] ?? '',
        $data['last_name'] ?? '',
        $data['email'] ?? '',
        $data['phone'] ?? '',
        $data['date_of_birth'] ?? null,
        $data['gender'] ?? '',
        $data['address'] ?? '',
        $data['city'] ?? '',
        $data['country'] ?? '',
        $data['parent_name'] ?? '',
        $data['parent_phone'] ?? '',
        $data['status'] ?? 'Active',
        $id
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Student updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update student']);
    }
}

function deleteStudent() {
    $db = getDB();
    $id = $_GET['id'] ?? 0;
    
    $stmt = $db->prepare("DELETE FROM students WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Student deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete student']);
    }
}