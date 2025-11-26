<?php
header('Content-Type: application/json');

$db_file = __DIR__ . '/db/puantaj.json';

function get_data() {
    global $db_file;
    if (!file_exists($db_file)) {
        file_put_contents($db_file, json_encode(['employees' => [], 'timesheets' => []], JSON_PRETTY_PRINT));
    }
    $json_data = file_get_contents($db_file);
    return json_decode($json_data, true);
}

function save_data($data) {
    global $db_file;
    file_put_contents($db_file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
}

function recursive_sanitize(&$item) {
    if (is_string($item)) {
        $item = htmlspecialchars($item, ENT_QUOTES, 'UTF-8');
    } elseif (is_array($item)) {
        foreach ($item as &$value) {
            recursive_sanitize($value);
        }
    }
}

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = get_data();
    $input = json_decode(file_get_contents('php://input'), true);

    if ($input) {
        recursive_sanitize($input);
    }

    if (empty($action) && isset($input['action'])) {
        $action = $input['action'];
    }

    switch ($action) {
        // Employee Actions
        case 'add_employee':
            $new_employee = $input['employee'];
            $new_employee['id'] = uniqid();
            $data['employees'][] = $new_employee;
            save_data($data);
            echo json_encode(['success' => true, 'employee' => $new_employee]);
            break;

        case 'edit_employee':
            $updated_employee = $input['employee'];
            foreach ($data['employees'] as &$employee) {
                if ($employee['id'] == $updated_employee['id']) {
                    $employee = $updated_employee;
                    break;
                }
            }
            save_data($data);
            echo json_encode(['success' => true, 'employee' => $updated_employee]);
            break;

        case 'delete_employee':
            $employee_id = $input['employee_id'];
            $data['employees'] = array_filter($data['employees'], function($employee) use ($employee_id) {
                return $employee['id'] != $employee_id;
            });
            // Also delete related timesheets
            $data['timesheets'] = array_filter($data['timesheets'], function($timesheet) use ($employee_id) {
                return $timesheet['employee_id'] != $employee_id;
            });
            save_data($data);
            echo json_encode(['success' => true]);
            break;

        // Timesheet Actions
        case 'add_timesheet':
            $new_timesheet = $input['timesheet'];
            $new_timesheet['id'] = uniqid();
            $data['timesheets'][] = $new_timesheet;
            save_data($data);
            echo json_encode(['success' => true, 'timesheet' => $new_timesheet]);
            break;

        case 'edit_timesheet':
            $updated_timesheet = $input['timesheet'];
            foreach ($data['timesheets'] as &$timesheet) {
                if ($timesheet['id'] == $updated_timesheet['id']) {
                    $timesheet = $updated_timesheet;
                    break;
                }
            }
            save_data($data);
            echo json_encode(['success' => true, 'timesheet' => $updated_timesheet]);
            break;

        case 'delete_timesheet':
            $timesheet_id = $input['timesheet_id'];
            $data['timesheets'] = array_filter($data['timesheets'], function($timesheet) use ($timesheet_id) {
                return $timesheet['id'] != $timesheet_id;
            });
            save_data($data);
            echo json_encode(['success' => true]);
            break;

        case 'import_json':
            if (isset($_FILES['json_file'])) {
                $file_tmp = $_FILES['json_file']['tmp_name'];
                $file_content = file_get_contents($file_tmp);
                $imported_data = json_decode($file_content, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    recursive_sanitize($imported_data);
                    save_data($imported_data);
                    echo json_encode(['success' => true, 'message' => 'Veri başarıyla içe aktarıldı.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Geçersiz JSON dosyası.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Dosya yüklenmedi.']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Geçersiz eylem.']);
            break;
    }
} else { // GET Requests
    $data = get_data();
    switch ($action) {
        case 'get_employees':
            echo json_encode($data['employees']);
            break;

        case 'get_timesheets_by_employee':
            $employee_id = $_GET['employee_id'];
            $employee_timesheets = array_filter($data['timesheets'], function($timesheet) use ($employee_id) {
                return $timesheet['employee_id'] == $employee_id;
            });
            echo json_encode(array_values($employee_timesheets));
            break;

        case 'export_json':
            header('Content-Disposition: attachment; filename="puantaj.json"');
            echo json_encode($data, JSON_PRETTY_PRINT);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Geçersiz eylem.']);
            break;
    }
}
?>
