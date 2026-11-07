<?php
/**
 * @var int $year
 * @var int $month
 * @var array $days
 * @var array $departments
 * @var array $departmentRows
 * @var array $employees
 * @var array $filials
 * @var array $schedule
 * @var array $shiftStatus
 */

$title = 'График';
$this->pageTitle = $title . Yii::app()->name;

$months = array(
    1=>'Январь',2=>'Февраль',3=>'Март',4=>'Апрель',
    5=>'Май',6=>'Июнь',7=>'Июль',8=>'Август',
    9=>'Сентябрь',10=>'Октябрь',11=>'Ноябрь',12=>'Декабрь',
);

$weekdays = array('Вс','Пн','Вт','Ср','Чт','Пт','Сб');

$mode = Yii::app()->request->getParam('mode', 'all');
$isMyMode = ($mode === 'my');

$isPrivileged = Yii::app()->user->checkAccess('administrator')
             || Yii::app()->user->checkAccess('supervisor');
$isAdmin = Yii::app()->user->checkAccess('administrator');

$filialPhones = (isset($filialPhones) && is_array($filialPhones)) ? $filialPhones : array();

$isHiddenDemoEmployee = function ($employee) use ($isAdmin) {
    if ($isAdmin) return false;
    $name = isset($employee['name']) ? trim((string)$employee['name']) : '';
    if (function_exists('mb_strtolower')) $name = mb_strtolower($name, 'UTF-8');
    else $name = strtolower($name);
    $name = str_replace('ё', 'е', $name);
    $name = preg_replace('/\s+/u', ' ', $name);
    return in_array($name, array('test', 'тест', 'не определен'), true);
};

$designMode = 'modern';
$employeePreferenceTargets = $filials + array(-1=>'Офис', -2=>'Цех');

$currentEmployeeId = Yii::app()->user->getState('employee_id');
if (!$currentEmployeeId) {
    $currentEmployeeId = (int) Yii::app()->user->id;
}
$currentEmployee = isset($employees[$currentEmployeeId])
    ? $employees[$currentEmployeeId]
    : null;

$assignMap = array();
if (!empty($schedule['filial'])) {
    foreach ($schedule['filial'] as $fid => $daysData) {
        foreach ((array)$daysData as $d => $empIds) {
            foreach ((array)$empIds as $empId) {
                if (!isset($assignMap[$d][$empId])) $assignMap[$d][$empId] = array();
                $assignMap[$d][$empId][] = 'filial_' . $fid;
            }
        }
    }
}
if (!empty($departmentRows)) {
    foreach ($departmentRows as $depKey => $rows) {
        foreach ($rows as $row) {
            if (!empty($row['employee_id'])) {
                foreach ($days as $d) {
                    if (!empty($schedule['department'][$row['id']][$d])) {
                        if (!isset($assignMap[$d][$row['employee_id']])) $assignMap[$d][$row['employee_id']] = array();
                        $assignMap[$d][$row['employee_id']][] = 'dep_' . $row['id'];
                    }
                }
            }
        }
    }
}

$employeeWorkplaces = array();
if (!empty($schedule['filial'])) {
    foreach ($schedule['filial'] as $fid => $daysData) {
        $fname = isset($filials[$fid]) ? $filials[$fid] : '?';
        foreach ((array)$daysData as $d => $empIds) {
            foreach ((array)$empIds as $empId) {
                if (!isset($employeeWorkplaces[$empId][$d])) $employeeWorkplaces[$empId][$d] = array();
                $employeeWorkplaces[$empId][$d][$fname] = array('type' => 'filial', 'id' => $fid, 'name' => $fname);
            }
        }
    }
}
if (!empty($departmentRows)) {
    foreach ($departmentRows as $depKey => $rows) {
        $depTitle = isset($departments[$depKey]) ? $departments[$depKey] : 'Отдел';
        foreach ($rows as $row) {
            $empId = isset($row['employee_id']) ? $row['employee_id'] : null;
            if (!$empId) continue;
            foreach ($days as $d) {
                if (!empty($schedule['department'][$row['id']][$d])) {
                    if (!isset($employeeWorkplaces[$empId][$d])) $employeeWorkplaces[$empId][$d] = array();
                    $employeeWorkplaces[$empId][$d][$depTitle] = array('type' => 'department', 'id' => $row['id'], 'name' => $depTitle);
                }
            }
        }
    }
}

$schedulePlaceNames = array();
foreach ($filials as $fid => $fname) {
    $schedulePlaceNames['filial_' . $fid] = $fname;
}
foreach ($departmentRows as $depKey => $rows) {
    $depTitle = isset($departments[$depKey]) ? $departments[$depKey] : 'Отдел';
    foreach ($rows as $row) {
        $schedulePlaceNames['dep_' . $row['id']] = $depTitle;
    }
}

$newTableEmployees = array();
if ($isMyMode) {
    if (isset($employees[$currentEmployeeId]) && $employees[$currentEmployeeId]['active'] != 0 && !$isHiddenDemoEmployee($employees[$currentEmployeeId])) {
        $newTableEmployees = array($currentEmployeeId);
    }
} else {
    foreach ($employees as $empId => $emp) {
        if ($emp['active'] != 0 && !$isHiddenDemoEmployee($emp)) $newTableEmployees[] = $empId;
    }
    usort($newTableEmployees, function ($a, $b) use ($employees, $isAdmin) {
        $nameA = trim((string)$employees[$a]['name']);
        $nameB = trim((string)$employees[$b]['name']);
        $normA = function_exists('mb_strtolower') ? mb_strtolower($nameA, 'UTF-8') : strtolower($nameA);
        $normB = function_exists('mb_strtolower') ? mb_strtolower($nameB, 'UTF-8') : strtolower($nameB);
        $normA = str_replace('ё', 'е', $normA);
        $normB = str_replace('ё', 'е', $normB);
        $demoNames = array('test', 'тест', 'не определен');
        $demoA = $isAdmin && in_array($normA, $demoNames, true) ? 1 : 0;
        $demoB = $isAdmin && in_array($normB, $demoNames, true) ? 1 : 0;
        if ($demoA !== $demoB) return $demoA - $demoB;
        return strcasecmp($nameA, $nameB);
    });
}

$today = date('Y-m-d');
$todayWd = $weekdays[date('w')];
$todayDayNumber = (int)date('N');

$normalizeSchedulePreferenceDay = function ($value) {
    if (!is_scalar($value)) return 0;
    $value = trim((string)$value);
    if ($value === '') return 0;
    if (is_numeric($value)) {
        $number = (int)$value;
        if ($number === 0) $number = 7;
        return ($number >= 1 && $number <= 7) ? $number : 0;
    }
    $value = rtrim($value, '.');
    $lower = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $lower = str_replace('ё', 'е', $lower);
    $map = array(
        'пн'=>1, 'понедельник'=>1,
        'вт'=>2, 'вторник'=>2,
        'ср'=>3, 'среда'=>3,
        'чт'=>4, 'четверг'=>4,
        'пт'=>5, 'пятница'=>5,
        'сб'=>6, 'суббота'=>6,
        'вс'=>7, 'воскресенье'=>7,
    );
    return isset($map[$lower]) ? $map[$lower] : 0;
};

$employeeHasPreferredDay = function ($employee) use ($todayDayNumber, $normalizeSchedulePreferenceDay) {
    if (!is_array($employee) || !array_key_exists('schedule_filial_preferences', $employee)) {
        return false;
    }

    $rawPreferences = $employee['schedule_filial_preferences'];

    if (is_string($rawPreferences)) {
        $decoded = json_decode($rawPreferences, true);
        $rawPreferences = is_array($decoded) ? $decoded : array();
    } elseif (is_object($rawPreferences)) {
        $rawPreferences = (array)$rawPreferences;
    }

    if (!is_array($rawPreferences) || empty($rawPreferences)) {
        return false;
    }

    $preferenceRows = isset($rawPreferences['days'])
        ? array($rawPreferences)
        : $rawPreferences;

    foreach ($preferenceRows as $preference) {
        if (is_object($preference)) $preference = (array)$preference;

        $days = (is_array($preference) && array_key_exists('days', $preference))
            ? $preference['days']
            : $preference;

        if (is_string($days)) {
            $decodedDays = json_decode($days, true);
            $days = is_array($decodedDays) ? $decodedDays : explode(',', $days);
        } elseif (!is_array($days)) {
            $days = ($days === null || $days === '') ? array() : array($days);
        }

        foreach ($days as $day) {
            if ($normalizeSchedulePreferenceDay($day) === $todayDayNumber) {
                return true;
            }
        }
    }

    return false;
};

$employeeAutoStatus = function ($employeeId, $employee) use ($employeeWorkplaces, $today, $employeeHasPreferredDay) {
    if (!empty($employeeWorkplaces[$employeeId][$today])) return 'shift';
    return $employeeHasPreferredDay($employee) ? 'free' : 'dayoff';
};

$activeEmployeesForStatus = array();
foreach ($employees as $empId => $emp) {
    if ($emp['active'] != 0 && !$isHiddenDemoEmployee($emp)) $activeEmployeesForStatus[$empId] = $emp;
}

$autoStatusLabels = array('shift' => 'На смене', 'free' => 'Свободен', 'dayoff' => 'Выходной');
$statusGroups = array(
    'engineers' => array('title' => 'Инженеры/Менеджеры', 'employees' => array(), 'shift_employees' => array()),
    'workshop'  => array('title' => 'Цех', 'employees' => array(), 'shift_employees' => array()),
    'office'    => array('title' => 'Офис', 'employees' => array(), 'shift_employees' => array()),
    'other'     => array('title' => 'Другое', 'employees' => array(), 'shift_employees' => array()),
);

$empStatusGroups = array();
$filialEmpIds = array();
if (!empty($schedule['filial'])) {
    foreach ($schedule['filial'] as $fid => $daysData) {
        foreach ((array)$daysData as $d => $empIds) {
            foreach ((array)$empIds as $empId) $filialEmpIds[$empId] = true;
        }
    }
}

$latestDeptPeriod = array();
$latestDeptCodes = array();
if (!empty($employeeDepartmentRows)) {
    foreach ($employeeDepartmentRows as $deptRow) {
        $empId = (int)$deptRow['employee_id'];
        if (!$empId) continue;
        $periodKey = ((int)$deptRow['year'] * 100) + (int)$deptRow['month'];
        if (!isset($latestDeptPeriod[$empId]) || $periodKey > $latestDeptPeriod[$empId]) {
            $latestDeptPeriod[$empId] = $periodKey;
            $latestDeptCodes[$empId] = array();
        }
        if ($periodKey === $latestDeptPeriod[$empId]) $latestDeptCodes[$empId][(string)$deptRow['department']] = true;
    }
}

foreach ($latestDeptCodes as $empId => $deptCodes) {
    if (isset($deptCodes['ceh'])) $empStatusGroups[$empId] = 'workshop';
    elseif (isset($deptCodes['office'])) $empStatusGroups[$empId] = 'office';
    elseif (isset($deptCodes['kc']) || isset($deptCodes['logistics'])) $empStatusGroups[$empId] = 'other';
}

foreach ($filialEmpIds as $empId => $true) {
    if (!isset($empStatusGroups[$empId]) || $empStatusGroups[$empId] === 'other') $empStatusGroups[$empId] = 'engineers';
}

foreach ($activeEmployeesForStatus as $empId => $emp) {
    $post = trim((string)(isset($emp['post']) ? $emp['post'] : ''));
    $postLower = function_exists('mb_strtolower') ? mb_strtolower($post, 'UTF-8') : strtolower($post);
    if (strpos($postLower, 'инженер') !== false || strpos($postLower, 'менеджер') !== false) {
        if (!isset($empStatusGroups[$empId]) || $empStatusGroups[$empId] === 'other') $empStatusGroups[$empId] = 'engineers';
    }
}

foreach ($activeEmployeesForStatus as $empId => $emp) {
    $nameLower = function_exists('mb_strtolower') ? mb_strtolower(trim($emp['name'])) : strtolower(trim($emp['name']));
    if (in_array($nameLower, array('test', 'тест', 'не определён', 'не определен'))) {
        $statusGroups['other']['employees'][$empId] = $emp;
        continue;
    }
    $grp = isset($empStatusGroups[$empId]) ? $empStatusGroups[$empId] : 'other';
    $statusGroups[$grp]['employees'][$empId] = $emp;
}

foreach ($statusGroups as &$sGroup) {
    $shiftEmps = array();
    $regularEmps = array();
    foreach ($sGroup['employees'] as $empId => $sEmp) {
        $sEmp['_auto_status'] = $employeeAutoStatus($empId, $sEmp);
        $sEmp['_state'] = isset($sEmp['schedule_state']) ? $sEmp['schedule_state'] : 'normal';
        if ($sEmp['_auto_status'] === 'shift' && $sEmp['_state'] === 'normal') $shiftEmps[$empId] = $sEmp;
        else $regularEmps[$empId] = $sEmp;
    }
    uasort($regularEmps, function ($a, $b) {
        $priority = array('free'=>1, 'sick'=>2, 'vacation'=>3, 'dayoff'=>4);
        $stateA = $a['_state'] !== 'normal' ? $a['_state'] : $a['_auto_status'];
        $stateB = $b['_state'] !== 'normal' ? $b['_state'] : $b['_auto_status'];
        $pA = isset($priority[$stateA]) ? $priority[$stateA] : 5;
        $pB = isset($priority[$stateB]) ? $priority[$stateB] : 5;
        if ($pA !== $pB) return $pA - $pB;
        return strcasecmp($a['name'], $b['name']);
    });
    uasort($shiftEmps, function ($a, $b) { return strcasecmp($a['name'], $b['name']); });
    $sGroup['employees'] = $regularEmps;
    $sGroup['shift_employees'] = $shiftEmps;
}
unset($sGroup);

$phoneSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>';

$quittingEmployees = array();
if ($isAdmin) {
    $quittingEmployees = Yii::app()->db->createCommand()->select('name, schedule_quitting_date as date')->from('service_employee')->where('schedule_is_quitting = 1 AND active = 1')->order('schedule_quitting_date ASC')->queryAll();
}

// Данные для CSV формируются прямо во view, без отдельного action контроллера.
$csvExportRows = array();
$csvWeekdays = array('Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота');
$csvShiftLabels = array('open' => 'Открыта', 'closed' => 'Зафиксирована', 'not_closed' => 'Не зафиксирована');
foreach ((array)$days as $csvDate) {
    $csvWeekday = $csvWeekdays[(int)date('w', strtotime($csvDate))];

    // Назначения по филиалам.
    if (!empty($schedule['filial'])) {
        foreach ($schedule['filial'] as $csvFilialId => $csvFilialDays) {
            if (empty($csvFilialDays[$csvDate])) continue;
            $csvEmployeeIds = (array)$csvFilialDays[$csvDate];
            foreach ($csvEmployeeIds as $csvEmployeeId) {
                if ($isMyMode && (string)$csvEmployeeId !== (string)$currentEmployeeId) continue;
                if (!isset($employees[$csvEmployeeId])) continue;

                $csvEmployee = $employees[$csvEmployeeId];
                $csvShiftCode = isset($shiftStatus[$csvFilialId][$csvDate][$csvEmployeeId])
                    ? $shiftStatus[$csvFilialId][$csvDate][$csvEmployeeId]
                    : '';
                $csvEmployeeState = isset($csvEmployee['schedule_state']) ? $csvEmployee['schedule_state'] : 'normal';
                $csvEmployeeStateLabel = $csvEmployeeState === 'sick' ? 'Болеет' : ($csvEmployeeState === 'vacation' ? 'В отпуске' : 'В норме');
                $csvFilialName = isset($filials[$csvFilialId]) ? $filials[$csvFilialId] : 'Филиал';
                $csvComment = isset($scheduleComments[$csvFilialId][$csvDate]) ? $scheduleComments[$csvFilialId][$csvDate] : '';

                $csvExportRows[] = array(
                    date('d.m.Y', strtotime($csvDate)), $csvWeekday, 'Филиал', $csvFilialName, '',
                    isset($csvEmployee['name']) ? $csvEmployee['name'] : '',
                    isset($csvEmployee['phone']) ? $csvEmployee['phone'] : '',
                    isset($csvShiftLabels[$csvShiftCode]) ? $csvShiftLabels[$csvShiftCode] : '',
                    $csvEmployeeStateLabel, $csvComment
                );
            }
        }
    }

    // Назначения по отделам.
    if (!empty($departmentRows)) {
        foreach ($departmentRows as $csvDepartmentKey => $csvRows) {
            $csvDepartmentTitle = isset($departments[$csvDepartmentKey]) ? $departments[$csvDepartmentKey] : 'Отдел';
            foreach ((array)$csvRows as $csvRow) {
                $csvEmployeeId = isset($csvRow['employee_id']) ? $csvRow['employee_id'] : 0;
                if (!$csvEmployeeId || empty($schedule['department'][$csvRow['id']][$csvDate])) continue;
                if ($isMyMode && (string)$csvEmployeeId !== (string)$currentEmployeeId) continue;
                if (!isset($employees[$csvEmployeeId])) continue;

                $csvEmployee = $employees[$csvEmployeeId];
                $csvEmployeeState = isset($csvEmployee['schedule_state']) ? $csvEmployee['schedule_state'] : 'normal';
                $csvEmployeeStateLabel = $csvEmployeeState === 'sick' ? 'Болеет' : ($csvEmployeeState === 'vacation' ? 'В отпуске' : 'В норме');
                $csvExportRows[] = array(
                    date('d.m.Y', strtotime($csvDate)), $csvWeekday, 'Отдел', $csvDepartmentTitle,
                    isset($csvRow['role']) ? $csvRow['role'] : '',
                    isset($csvEmployee['name']) ? $csvEmployee['name'] : '',
                    isset($csvEmployee['phone']) ? $csvEmployee['phone'] : '',
                    'Назначен', $csvEmployeeStateLabel, ''
                );
            }
        }
    }
}
?>

<style>
.schedule-page  {
    
    --bg: #fbfbfe;
    --card: #ffffff;
    --text: #0f172a;
    --muted: #64748b;
    --line: #e7e8f0;
    --line-2: #d3d5e2;
    --primary: #7655c7;
    --primary-hover: #6443b4;
    --radius: 12px;
    --shadow-sm: 0 2px 7px rgba(37,30,70,.045);
    --shadow: 0 12px 28px rgba(37,30,70,.085);
    --s-open-bg: #e0f2fe;
    --s-open-line: #0ea5e9;
    --s-closed-bg: #dcfce7;
    --s-closed-line: #22c55e;
    --s-error-bg: #fee2e2;
    --s-error-line: #ef4444;
    --s-empty-bg: #fef3c7;
    --s-empty-line: #f59e0b;
    --role-w: 104px;
    --emp-w: 150px;
    --dept-day-w: 38px;
    --branch-w: 170px;
    --filial-day-w: 60px;
    width: 100vw;
    max-width: 100vw;
    margin-left: calc(50% - 50vw);
    margin-right: calc(50% - 50vw);
    padding: 20px clamp(10px, 3vw, 30px) 50px;
    background: var(--bg);
    color: var(--text);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 13.5px;
    line-height: 1.45;
    box-sizing: border-box;
    
}
.schedule-page *, .schedule-page *::before, .schedule-page *::after  {
     box-sizing: border-box;
    
}
html, body  {
     max-width: 100%;
    overflow-x: hidden !important;
    
}
.schedule-page  {
     max-width: 100vw;
    overflow-x: clip;
    
}
@keyframes cardFadeIn  {
     from  {
         opacity: 0;
        transform: translateY(8px);
        
    }
to  {
         opacity: 1;
        transform: translateY(0);
        
    }
}
.card  {
     animation: cardFadeIn .35s ease-out both;
    
}
@keyframes bannerIn  {
     from  {
         opacity: 0;
        transform: translateX(-6px);
        
    }
to  {
         opacity: 1;
        transform: translateX(0);
        
    }
}
.schedule-topbar  {
     display:flex;
    flex-wrap:wrap;
    gap:12px 16px;
    align-items:center;
    justify-content:space-between;
    margin-bottom:14px;
    
}
.schedule-title-block h1  {
     margin:0;
    font-size:clamp(19px,3vw,26px);
    font-weight:800;
    letter-spacing:-.02em;
    color:#0f172a;
    
}
.schedule-subtitle  {
     color:var(--muted);
    font-size:13px;
    margin-top:1px;
    font-weight:500;
    
}
.legend  {
     display:flex;
    flex-wrap:wrap;
    gap:5px;
    
}
.legend-item  {
     display:inline-flex;
    align-items:center;
    gap:6px;
    padding:3px 9px;
    border-radius:999px;
    background:var(--card);
    border:1px solid var(--line);
    font-size:11px;
    color:#334155;
    box-shadow:var(--shadow-sm);
    white-space:nowrap;
    font-weight:600;
    
}
.legend-dot  {
     width:8px;
    height:8px;
    border-radius:2px;
    flex:0 0 auto;
    
}
.legend-note-symbol  {
     display:inline-flex;
    width:15px;
    height:15px;
    align-items:center;
    justify-content:center;
    border-radius:50%;
    border:1px solid #e5a02b;
    background:#fff1c8;
    color:#a96508;
    font-size:10px;
    font-weight:900;
    line-height:1;
    
}
.controls-card  {
     display:flex;
    flex-wrap:wrap;
    gap:10px 14px;
    align-items:center;
    padding:11px 14px;
    background:var(--card);
    border:1px solid var(--line);
    border-radius:var(--radius);
    box-shadow:var(--shadow-sm);
    margin-bottom:16px;
    
}
.control-group  {
     display:inline-flex;
    flex-wrap:wrap;
    align-items:center;
    gap:6px;
    margin:0;
    
}
.control-group label, .control-group .ctrl-label  {
     color:var(--muted);
    font-size:12px;
    font-weight:600;
    margin:0;
    
}
.control-group select  {
     appearance:none;
    -webkit-appearance:none;
    padding:5px 26px 5px 9px;
    border-radius:7px;
    border:1px solid var(--line-2);
    background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E") no-repeat right 8px center;
    font-size:12px;
    color:var(--text);
    cursor:pointer;
    transition:border-color .15s,box-shadow .15s;
    
}
.control-group select:focus  {
     outline:none;
    border-color:var(--primary);
    box-shadow:0 0 0 2px rgba(124,58,237,.15);
    
}
.btn-primary-x  {
     padding:6px 16px;
    border-radius:8px;
    border:none;
    background-color:var(--primary);
    color:#fff;
    font-weight:600;
    font-size:13px;
    cursor:pointer;
    transition:background-color .2s,transform .1s;
    
}
.btn-primary-x:hover  {
     background-color:var(--primary-hover);
    transform:translateY(-1px);
    
}
.btn-primary-x:active  {
     transform:translateY(0);
    
}
.mode-btn  {
     padding:6px 14px;
    border-radius:8px;
    border:1px solid var(--line-2);
    background:#fff;
    color:#334155;
    font-weight:600;
    font-size:13px;
    cursor:pointer;
    transition:all .15s;
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    line-height:1.2;
    
}
.mode-btn:hover  {
     border-color:var(--primary);
    color:var(--primary);
    text-decoration:none;
    transform:translateY(-1px);
    
}
.mode-btn.active  {
     background:var(--primary);
    color:#fff;
    border-color:var(--primary);
    
}
.mode-btn.mode-btn-success  {
     background:#16a34a;
    color:#fff;
    border-color:#16a34a;
    
}
.mode-btn.mode-btn-success:hover  {
     background:#15803d;
    border-color:#15803d;
    color:#fff;
    
}
.my-banner  {
     display:flex;
    align-items:center;
    gap:10px;
    padding:10px 16px;
    background:linear-gradient(135deg,#f3e8ff,#ede9fe);
    border:1px solid #d8b4fe;
    border-radius:var(--radius);
    margin-bottom:16px;
    box-shadow:var(--shadow-sm);
    animation:bannerIn .3s ease-out both;
    
}
.my-banner-title  {
     font-size:11px;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.06em;
    color:var(--primary);
    
}
.my-banner-sub  {
     font-size:14.5px;
    font-weight:800;
    color:#1e293b;
    margin-top:1px;
    
}
.birthdays-card, .quitting-card  {
     background:var(--card);
    border:1px solid var(--line);
    border-left:4px solid #ef4444;
    border-radius:var(--radius);
    padding:10px 14px;
    margin-bottom:20px;
    box-shadow:var(--shadow-sm);
    
}
.birthdays-card h3, .quitting-card h3  {
     margin:0 0 8px;
    font-size:13px;
    font-weight:800;
    display:flex;
    align-items:center;
    gap:6px;
    
}
.birthdays-card h3  {
     color:#1e293b;
    
}
.quitting-card h3  {
     color:#991b1b;
    
}
.birthdays-list  {
     display:flex;
    flex-wrap:wrap;
    gap:6px;
    margin:0;
    padding:0;
    list-style:none;
    
}
.birthdays-list li  {
     padding:3px 10px;
    border-radius:999px;
    font-size:11.5px;
    font-weight:600;
    
}
.birthdays-card .birthdays-list li  {
     background:#fdf2f8;
    color:#9d174d;
    
}
.quitting-card .birthdays-list li  {
     background:#fee2e2;
    color:#b91c1c;
    
}
.birthdays-empty  {
     color:var(--muted);
    font-size:12.5px;
    margin:0;
    
}
.section-title  {
     display:flex;
    align-items:center;
    gap:8px;
    font-size:15px;
    font-weight:800;
    margin:22px 0 10px;
    color:#1e293b;
    letter-spacing:-.01em;
    
}
.section-bar  {
     width:5px;
    height:20px;
    border-radius:3px;
    background:var(--primary);
    flex:0 0 auto;
    
}
.card  {
     background:var(--card);
    border:1px solid var(--line);
    border-radius:var(--radius);
    box-shadow:var(--shadow-sm);
    margin-bottom:14px;
    transition:box-shadow .24s ease,border-color .24s ease,transform .24s ease;
    
}
.card:hover  {
     box-shadow:var(--shadow);
    border-color:#ded9ef;
    
}
.card-header-x  {
     display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
    padding:9px 14px;
    border-bottom:1px solid var(--line);
    background:linear-gradient(180deg,#fafbfc,#f3f5f9);
    border-radius:var(--radius) var(--radius) 0 0;
    
}
.card-header-x h3  {
     margin:0;
    font-size:13px;
    font-weight:800;
    color:#1e293b;
    
}
.card-badge  {
     font-size:10px;
    font-weight:700;
    color:#475569;
    background:#e2e8f0;
    border-radius:999px;
    padding:2px 9px;
    white-space:nowrap;
    
}
.table-scroll  {
     width:100%;
    max-height:none;
    overflow-x:auto;
    overflow-y:hidden;
    -webkit-overflow-scrolling:touch;
    scrollbar-width:thin;
    
}
.table-scroll::-webkit-scrollbar  {
     height:6px;
    
}
.table-scroll::-webkit-scrollbar-thumb  {
     background:#cbd5e1;
    border-radius:3px;
    
}
.table-scroll::-webkit-scrollbar-track  {
     background:transparent;
    
}
.schedule-table  {
     border-collapse:separate;
    border-spacing:0;
    table-layout:fixed;
    width:auto;
    min-width:100%;
    white-space:nowrap;
    font-size:12px;
    
}
.schedule-table th, .schedule-table td  {
     padding:6px 5px;
    border-bottom:1px solid var(--line);
    border-right:1px solid var(--line);
    text-align:center;
    vertical-align:middle;
    background:#fff;
    
}
.schedule-table th:last-child, .schedule-table td:last-child  {
     border-right:0;
    
}
.schedule-table thead th  {
     position:sticky;
    top:0;
    z-index:5;
    background:#f8fafc;
    color:#475569;
    font-weight:700;
    font-size:10.5px;
    text-transform:uppercase;
    letter-spacing:.03em;
    padding:6px 3px;
    border-bottom:1px solid var(--line-2);
    
}
.day-head  {
     line-height:1.2;
    
}
.d-wd  {
     font-size:9.5px;
    color:#64748b;
    text-transform:uppercase;
    
}
.d-dt  {
     font-size:12px;
    font-weight:800;
    color:#0f172a;
    margin-top:2px;
    
}
.schedule-table .col-role, .schedule-table .col-emp  {
     position:sticky;
    z-index:3;
    background:#fff;
    text-align:left;
    overflow:hidden;
    text-overflow:ellipsis;
    
}
.schedule-table .col-role  {
     left:0;
    width:var(--role-w);
    min-width:var(--role-w);
    color:#475569;
    font-size:11px;
    font-weight:600;
    padding-left:10px;
    
}
.schedule-table .col-emp  {
     left:var(--role-w);
    width:var(--emp-w);
    min-width:var(--emp-w);
    padding-left:8px;
    box-shadow:8px 0 10px -8px rgba(15,23,42,.22);
    
}
.schedule-table .col-branch  {
     position:sticky;
    left:0;
    z-index:3;
    background:#fff;
    text-align:left;
    width:var(--branch-w);
    min-width:var(--branch-w);
    padding:6px 10px;
    overflow:hidden;
    text-overflow:ellipsis;
    box-shadow:8px 0 10px -8px rgba(15,23,42,.22);
    font-weight:700;
    color:#1e293b;
    font-size:12px;
    
}
.schedule-table thead .col-role, .schedule-table thead .col-emp, .schedule-table thead .col-branch  {
     z-index:6;
    background:#f8fafc;
    
}
.dept-table .day-head, .dept-table .day-cell  {
     width:var(--dept-day-w);
    min-width:var(--dept-day-w);
    
}
.filial-table .day-head, .filial-table .filial-day  {
     width:var(--filial-day-w);
    min-width:var(--filial-day-w);
    
}
.today-head  {
     background:linear-gradient(180deg,#ede4fd 0%,#e4d7fb 100%) !important;
    border-bottom:1px solid #ddd4f0 !important;
    position:sticky;
    
}
.today-head .d-wd, .today-head .d-dt  {
     color:#7c3aed !important;
    
}
.today-head .d-dt  {
     position:relative;
    
}
.today-head .d-dt::after  {
     content:'';
    display:block;
    width:6px;
    height:6px;
    background:#8b5cf6;
    border-radius:50%;
    margin:3px auto 0;
    box-shadow:0 0 0 3px rgba(139,92,246,.18);
    
}
.today-head::after  {
     display:none !important;
    
}
.today-cell  {
     background-color:#ebe0fc !important;
    
}
.day-check  {
     display:inline-flex;
    align-items:center;
    justify-content:center;
    width:24px;
    height:24px;
    background:linear-gradient(135deg,#ecfdf5 0%,#d1fae5 100%);
    color:#059669;
    border-radius:50%;
    border:1px solid #a7f3d0;
    box-shadow:0 1px 2px rgba(16,185,129,.12);
    line-height:1;
    
}
.day-check svg  {
     width:12px;
    height:12px;
    
}
.weekend-head  {
     color:#dc2626 !important;
    background:#fef2f2 !important;
    
}
.weekend-head .d-dt  {
     color:#dc2626;
    
}
.schedule-table td.weekend-cell  {
     background:#fdf5f5;
    
}
.day-empty  {
     display:inline-block;
    width:4px;
    height:4px;
    border-radius:50%;
    background:#e2e8f0;
    vertical-align:middle;
    
}
.emp-cell  {
     display:flex;
    flex-direction:row;
    align-items:center;
    gap:6px;
    min-width:0;
    
}
.emp-name  {
     display:block;
    max-width:100%;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
    font-weight:600;
    color:#0f172a;
    font-size:12px;
    
}
.emp-name s  {
     color:#94a3b8;
    
}
.call-mini  {
     display:inline-flex;
    align-items:center;
    justify-content:center;
    width:20px;
    height:20px;
    padding:0;
    border-radius:4px;
    background:transparent;
    color:#16a34a;
    border:none;
    text-decoration:none;
    cursor:pointer;
    transition:all .2s;
    flex-shrink:0;
    
}
.call-mini:hover  {
     color:#15803d;
    background:#f1f5f9;
    
}
.call-mini svg  {
     width:13px;
    height:13px;
    
}
.schedule-table .filial-day  {
     position:relative;
    text-align:left;
    padding:6px 6px 6px 16px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
    font-size:11.5px;
    
}
.filial-day::before  {
     content:"";
    position:absolute;
    left:6px;
    top:50%;
    transform:translateY(-50%);
    width:5px;
    height:5px;
    border-radius:50%;
    background:transparent;
    
}
.filial-name  {
     display:block;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
    font-weight:600;
    color:#0f172a;
    
}
.filial-name s  {
     color:#94a3b8;
    
}
.empty-mark  {
     color:#b45309;
    font-weight:700;
    
}
.schedule-table td.shift-open  {
     background:var(--s-open-bg);
    
}
.schedule-table td.shift-closed  {
     background:var(--s-closed-bg);
    
}
.schedule-table td.shift-error  {
     background:var(--s-error-bg);
    
}
.schedule-table td.empty-cell  {
     background:var(--s-empty-bg);
    
}
.filial-day.shift-open::before  {
     background:var(--s-open-line);
    
}
.filial-day.shift-closed::before  {
     background:var(--s-closed-line);
    
}
.filial-day.shift-error::before  {
     background:var(--s-error-line);
    
}
.filial-day.empty-cell::before  {
     background:var(--s-empty-line);
    
}
.emp-branch-table .filial-day  {
     width:78px;
    min-width:78px;
    
}
.emp-branch-table .filial-day.no-shift  {
     background:#fafbfc;
    
}
.emp-branch-table .no-shift-mark  {
     color:#cbd5e1;
    font-weight:600;
    
}
.filials-layout  {
     display:flex;
    gap:14px;
    align-items:flex-start;
    margin-bottom:30px;
    flex-wrap:nowrap;
    width:100%;
    min-width:0;
    
}
.filials-main  {
     flex:1 1 0;
    min-width:0;
    width:0;
    align-self:flex-start;
    
}
.rest-main  {
     flex:0 0 340px;
    min-width:280px;
    max-width:36%;
    display:flex;
    flex-direction:column;
    align-self:flex-start;
    height:auto;
    min-height:0;
    max-height:none;
    overflow:hidden;
    
}
.filials-main .card  {
     margin-bottom:0;
    height:auto;
    min-width:0;
    
}
.filials-main .table-scroll  {
     overflow-y:hidden;
    max-width:100%;
    
}
.rest-main .card  {
     display:flex;
    flex:1 1 auto;
    flex-direction:column;
    margin-bottom:0;
    height:100%;
    min-height:0;
    max-height:100%;
    overflow:hidden;
    
}
.rest-body  {
     padding:10px 14px;
    border-bottom:1px solid var(--line);
    flex:0 0 auto;
    
}
.rest-date  {
     width:100%;
    padding:7px 10px;
    border-radius:7px;
    border:1px solid var(--line-2);
    font-size:12.5px;
    color:var(--text);
    background:#fff;
    margin-bottom:8px;
    
}
.rest-date:focus  {
     outline:none;
    border-color:var(--primary);
    box-shadow:0 0 0 2px rgba(124,58,237,.15);
    
}
.status-container  {
     flex:1 1 0;
    height:0;
    overflow-y:auto;
    overflow-x:hidden;
    min-height:0;
    max-height:none;
    padding:0;
    
}
.status-container::-webkit-scrollbar  {
     width:6px;
    
}
.status-container::-webkit-scrollbar-thumb  {
     background:#cbd5e1;
    border-radius:3px;
    
}
.status-group  {
     padding:12px 14px;
    border-bottom:1px solid var(--line);
    
}
.status-group:last-child  {
     border-bottom:none;
    
}
.status-group-title  {
     font-size:11px;
    font-weight:800;
    text-transform:uppercase;
    color:#64748b;
    letter-spacing:.5px;
    margin-bottom:8px;
    display:flex;
    align-items:center;
    gap:6px;
    
}
.status-item  {
     display:flex;
    align-items:center;
    justify-content:space-between;
    padding:8px 0;
    border-bottom:1px dashed #f1f5f9;
    
}
.status-item:last-child  {
     border-bottom:none;
    
}
.status-item-info  {
     display:flex;
    flex-direction:row;
    align-items:center;
    overflow:hidden;
    flex:1;
    margin-right:10px;
    gap:10px;
    
}
.status-avatar  {
     width:32px;
    height:32px;
    border-radius:50%;
    object-fit:cover;
    flex-shrink:0;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:13px;
    font-weight:bold;
    background:#e2e8f0;
    color:#475569;
    border:1px solid #cbd5e1;
    
}
.status-name  {
     font-size:12.5px;
    font-weight:600;
    color:#1e293b;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
    display:inline-flex;
    align-items:center;
    gap:6px;
    width:fit-content;
    max-width:100%;
    
}
.status-actions  {
     display:flex;
    align-items:center;
    gap:6px;
    flex-shrink:0;
    
}
.status-badge  {
     display:inline-flex;
    align-items:center;
    gap:4px;
    padding:2px 8px;
    border-radius:6px;
    font-size:10px;
    font-weight:700;
    white-space:nowrap;
    
}
.status-badge.shift  {
     background:#f3e8ff;
    color:#7c3aed;
    border:1px solid #d8b4fe;
    
}
.status-badge.sick  {
     background:#fee2e2;
    color:#b91c1c;
    border:1px solid #fecaca;
    
}
.status-badge.vacation  {
     background:#ffedd5;
    color:#c2410c;
    border:1px solid #fed7aa;
    
}
.status-badge.free  {
     background:#dcfce7;
    color:#15803d;
    border:1px solid #bbf7d0;
    
}
.status-badge.dayoff  {
     background:#e0f2fe;
    color:#0369a1;
    border:1px solid #bae6fd;
    
}
.shift-toggle  {
     display:flex;
    align-items:center;
    justify-content:space-between;
    padding:8px 10px;
    margin-top:6px;
    background:#f8fafc;
    border:1px dashed #cbd5e1;
    border-radius:8px;
    font-size:11.5px;
    font-weight:700;
    color:#64748b;
    cursor:pointer;
    transition:all .2s;
    
}
.shift-toggle:hover  {
     background:#f1f5f9;
    color:var(--primary);
    border-color:#a78bfa;
    
}
.shift-toggle svg  {
     width:14px;
    height:14px;
    transition:transform .2s;
    
}
.shift-toggle.open svg  {
     transform:rotate(180deg);
    
}
.shift-employees-container  {
     margin-top:8px;
    padding-left:6px;
    border-left:2px solid #e2e8f0;
    
}
.emp-popup-trigger  {
     cursor:pointer;
    border-bottom:1px dashed #cbd5e1;
    transition:color .2s,border-color .2s;
    display:inline-block;
    
}
.emp-popup-trigger:hover  {
     color:var(--primary);
    border-bottom-color:var(--primary);
    
}
.emp-popup  {
     position:fixed !important;
    margin:0 !important;
    contain:layout paint;
    position:fixed;
    display:none;
    z-index:10000;
    background:#fff;
    border:1px solid #94a3b8;
    border-radius:12px;
    box-shadow:0 15px 30px -5px rgba(0,0,0,.25),0 0 0 1px rgba(0,0,0,.05);
    padding:12px;
    width:310px;
    max-width:calc(100vw - 24px);
    max-height:calc(100vh - 24px);
    overflow-y:auto;
    overflow-x:hidden;
    overscroll-behavior:contain;
    box-sizing:border-box;
    font-size:12px;
    color:var(--text);
    pointer-events:none;
    opacity:0;
    transform:translateY(5px);
    transition:opacity .2s cubic-bezier(.16,1,.3,1),transform .2s cubic-bezier(.16,1,.3,1);
    
}
.emp-popup.active  {
     display:block;
    pointer-events:auto;
    opacity:1;
    transform:translateY(0);
    
}
.emp-popup::before  {
     content:'';
    position:absolute;
    width:12px;
    height:12px;
    background:#fff;
    border:1px solid #94a3b8;
    transform:rotate(45deg);
    
}
.emp-popup.left-arrow::before  {
     right:-7px;
    top:var(--emp-popup-arrow-top,20px);
    left:auto;
    border-right:1px solid #94a3b8;
    border-top:1px solid #94a3b8;
    border-left:none;
    border-bottom:none;
    
}
.emp-popup.right-arrow::before  {
     left:-7px;
    top:var(--emp-popup-arrow-top,20px);
    right:auto;
    border-left:1px solid #94a3b8;
    border-bottom:1px solid #94a3b8;
    border-right:none;
    border-top:none;
    
}
.emp-popup-header  {
     display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin-bottom:10px;
    border-bottom:1px solid var(--line);
    padding-bottom:10px;
    
}
.emp-popup-header-left  {
     display:flex;
    align-items:center;
    gap:10px;
    
}
.emp-popup-name  {
     font-weight:800;
    font-size:13px;
    color:#1e293b;
    line-height:1.2;
    
}
.emp-popup-role  {
     color:#64748b;
    font-size:11px;
    margin-top:2px;
    
}
.popup-phone-right  {
     display:flex;
    flex-direction:column;
    align-items:flex-end;
    
}
.popup-phone-label  {
     font-size:9px;
    color:var(--muted);
    text-transform:uppercase;
    font-weight:700;
    margin-bottom:2px;
    
}
.popup-phone-wrap  {
     display:flex;
    align-items:center;
    gap:6px;
    justify-content:flex-end;
    
}
.popup-call-btn  {
     display:inline-flex;
    align-items:center;
    justify-content:center;
    width:24px;
    height:24px;
    border-radius:50%;
    background:#ecfdf5;
    color:#16a34a;
    border:1px solid #bbf7d0;
    cursor:pointer;
    transition:all .2s;
    padding:0;
    flex-shrink:0;
    
}
.popup-call-btn:hover  {
     background:#dcfce7;
    color:#15803d;
    transform:scale(1.05);
    
}
.popup-call-btn svg  {
     width:12px;
    height:12px;
    
}
.popup-phone-number  {
     font-size:12.5px;
    font-weight:700;
    color:#1e293b;
    cursor:pointer;
    transition:color .2s;
    white-space:nowrap;
    
}
.popup-phone-number:hover  {
     color:var(--primary);
    text-decoration:underline;
    
}
.emp-popup-avatar-wrap span, .emp-popup-avatar-wrap img  {
     width:40px;
    height:40px;
    border-radius:50%;
    object-fit:cover;
    flex-shrink:0;
    display:flex;
    align-items:center;
    justify-content:center;
    
}
.emp-popup-avatar-wrap span  {
     background:#f1f5f9;
    color:#475569;
    font-size:16px;
    font-weight:bold;
    border:1px solid #e2e8f0;
    
}
.emp-popup-avatar-wrap img  {
     cursor:pointer;
    transition:opacity .2s;
    
}
.emp-popup-avatar-wrap img:hover  {
     opacity:.8;
    
}
.emp-popup-body  {
     display:flex;
    flex-direction:column;
    gap:6px;
    
}
.popup-field  {
     display:flex;
    flex-direction:column;
    gap:3px;
    padding-bottom:6px;
    border-bottom:1px dashed #e2e8f0;
    
}
.popup-field:last-child  {
     border-bottom:none;
    padding-bottom:0;
    
}
.popup-field label  {
     font-size:10.5px;
    font-weight:700;
    color:var(--muted);
    text-transform:uppercase;
    letter-spacing:.3px;
    
}
.popup-control  {
     display:block;
    width:100%;
    padding:6px 8px;
    font-size:12px;
    font-family:inherit;
    color:var(--text);
    background:#f8fafc;
    border:1px solid var(--line);
    border-radius:6px;
    transition:all .2s ease;
    cursor:pointer;
    outline:none;
    box-sizing:border-box;
    
}
.popup-control:hover, .popup-control:focus  {
     background:#fff;
    border-color:var(--primary);
    box-shadow:0 0 0 2px rgba(124,58,237,.15);
    
}
textarea.popup-control  {
     resize:none;
    min-height:36px;
    line-height:1.4;
    
}
select.popup-state.state-normal  {
     background-color:#dcfce7;
    color:#15803d;
    border-color:#bbf7d0;
    font-weight:600;
    
}
select.popup-state.state-sick  {
     background-color:#fee2e2;
    color:#b91c1c;
    border-color:#fecaca;
    font-weight:600;
    
}
select.popup-state.state-vacation  {
     background-color:#ffedd5;
    color:#c2410c;
    border-color:#fed7aa;
    font-weight:600;
    
}
.popup-auto-status  {
     display:inline-flex;
    align-items:center;
    gap:6px;
    padding:6px 8px;
    border-radius:6px;
    font-size:12px;
    font-weight:600;
    cursor:default;
    background:#f8fafc;
    border:1px solid var(--line);
    
}
.popup-auto-status.status-shift  {
     background:#f3e8ff;
    color:#7c3aed;
    border-color:#d8b4fe;
    
}
.popup-auto-status.status-free  {
     background:#dcfce7;
    color:#15803d;
    border-color:#bbf7d0;
    
}
.popup-auto-status.status-dayoff  {
     background:#e0f2fe;
    color:#0369a1;
    border-color:#bae6fd;
    
}
.popup-quitting-row  {
     display:flex;
    gap:6px;
    align-items:center;
    
}
.popup-quitting-row .popup-quitting  {
     flex:0 0 70px;
    
}
select.popup-quitting.quitting-yes  {
     background-color:#fee2e2;
    color:#b91c1c;
    border-color:#fecaca;
    font-weight:600;
    
}
select.popup-quitting.quitting-no  {
     background-color:#dcfce7;
    color:#15803d;
    border-color:#bbf7d0;
    font-weight:600;
    
}
.popup-quitting-date-wrapper  {
     display:none;
    flex:1;
    gap:4px;
    align-items:center;
    
}
.popup-date-wrapper, .popup-quitting-date-wrapper  {
     display:none;
    margin-top:6px;
    gap:6px;
    align-items:center;
    flex-wrap:wrap;
    width:100%;
    
}
.popup-date-wrapper input[type="date"], .popup-quitting-date-wrapper input[type="date"]  {
     flex:1 1 100px;
    min-width:0;
    padding:4px 6px;
    font-size:11px;
    height:28px;
    font-family:inherit;
    border:1px solid var(--line);
    border-radius:6px;
    background:#f8fafc;
    color:var(--text);
    outline:none;
    cursor:pointer;
    transition:all .2s;
    
}
.popup-date-wrapper input[type="date"]:hover, .popup-quitting-date-wrapper input[type="date"]:hover  {
     border-color:var(--primary);
    background:#fff;
    
}
.popup-date-label  {
     font-size:11px;
    font-weight:600;
    color:var(--muted);
    flex:0 0 auto;
    
}
.popup-date-checkbox  {
     display:inline-flex;
    align-items:center;
    gap:4px;
    font-size:10px;
    color:var(--muted);
    cursor:pointer;
    white-space:nowrap;
    flex:0 0 auto;
    
}
.popup-days-grid  {
     display:flex;
    gap:3px;
    flex-wrap:wrap;
    margin-top:2px;
    
}
.day-pill  {
     display:inline-flex;
    align-items:center;
    justify-content:center;
    width:28px;
    height:28px;
    font-size:10px;
    font-weight:700;
    border-radius:6px;
    background:#f1f5f9;
    color:#64748b;
    border:1px solid transparent;
    cursor:pointer;
    transition:all .15s ease;
    user-select:none;
    
}
.day-pill:hover  {
     background:#e2e8f0;
    color:#334155;
    
}
.day-pill.active  {
     background:#ede9fe;
    color:#7c3aed;
    border-color:#c4b5fd;
    box-shadow:inset 0 0 0 1px rgba(124,58,237,.1);
    
}
.status-item.hidden, .status-group.hidden  {
     display:none !important;
    
}
#avatar-modal  {
     display:none;
    position:fixed;
    top:0;
    left:0;
    right:0;
    bottom:0;
    background:rgba(0,0,0,.8);
    z-index:99999;
    align-items:center;
    justify-content:center;
    cursor:pointer;
    
}
#avatar-modal img  {
     max-width:90vw;
    max-height:90vh;
    border-radius:8px;
    box-shadow:0 10px 30px rgba(0,0,0,.5);
    object-fit:contain;
    
}
.edit-only  {
     display:none;
    
}
.schedule-page.is-editing .edit-only  {
     display:block;
    
}
.schedule-page.is-editing .edit-hide  {
     display:none !important;
    
}
.schedule-page.is-editing td.filial-day, .schedule-page.is-editing td.day-cell  {
     cursor:pointer;
    
}
.schedule-page.is-editing td.filial-day:hover, .schedule-page.is-editing td.day-cell:hover  {
     background-color:#f1f5f9;
    box-shadow:inset 0 0 0 2px var(--primary);
    
}
.sort-btn  {
     cursor:pointer;
    font-size:14px;
    display:block;
    line-height:1;
    color:#64748b;
    
}
.sort-btn:hover  {
     color:var(--primary);
    
}
.del-row-btn  {
     color:#ef4444;
    cursor:pointer;
    font-weight:bold;
    font-size:16px;
    
}
.add-row-btn  {
     margin-top:10px;
    padding:6px 12px;
    background:#f8fafc;
    border:1px dashed #cbd5e1;
    border-radius:6px;
    cursor:pointer;
    font-size:12px;
    color:#475569;
    
}
.add-row-btn:hover  {
     background:#f1f5f9;
    
}
.cell-edit-select  {
     width:100%;
    padding:4px;
    border-radius:4px;
    border:1px solid #cbd5e1;
    
}
.editing-banner  {
     display:flex;
    align-items:center;
    gap:10px;
    padding:9px 13px;
    margin:0 0 14px;
    border:1px solid #f5d28a;
    border-radius:10px;
    background:linear-gradient(105deg,#fff8e8,#fffaf1);
    color:#8a5a12;
    box-shadow:0 2px 8px rgba(180,120,20,.06);
    animation:bannerIn .28s ease-out both;
    
}
.editing-banner-dot  {
     width:8px;
    height:8px;
    border-radius:50%;
    background:#e5a02b;
    box-shadow:0 0 0 4px rgba(229,160,43,.13);
    flex:0 0 auto;
    
}
.editing-banner strong  {
     font-size:11px;
    text-transform:uppercase;
    letter-spacing:.045em;
    
}
.editing-banner span:last-child  {
     color:#9a7134;
    font-size:11px;
    
}
.schedule-edit-actions  {
     display:flex !important;
    flex-wrap:wrap;
    gap:7px;
    margin-left:auto;
    
}
.schedule-edit-actions .mode-btn  {
     transition:transform .18s ease,box-shadow .18s ease,background .18s ease;
    
}
.schedule-edit-actions .mode-btn:hover  {
     transform:translateY(-1px);
    box-shadow:0 4px 12px rgba(15,23,42,.12);
    
}
.schedule-table td.filial-day  {
     padding-right:27px;
    
}
.schedule-note-indicator  {
     position:absolute;
    top:4px;
    right:4px;
    z-index:4;
    display:inline-flex;
    width:17px;
    height:17px;
    align-items:center;
    justify-content:center;
    border-radius:50%;
    border:1px solid #e5a02b;
    background:#fff1c8;
    color:#a96508;
    font-size:11px;
    line-height:1;
    font-weight:900;
    cursor:help;
    box-shadow:0 1px 3px rgba(135,90,10,.13);
    animation:noteAppear .28s ease-out both;
    transition:transform .15s ease,background .15s ease;
    
}
.schedule-note-indicator:hover  {
     background:#ffe6a0;
    transform:scale(1.08);
    
}
@keyframes noteAppear  {
     from  {
         opacity:0;
        
    }
to  {
         opacity:1;
        
    }
}
.schedule-note-tooltip  {
     position:fixed;
    display:block;
    z-index:100020;
    max-width:min(320px,calc(100vw - 24px));
    min-width:170px;
    padding:11px 14px;
    border:1px solid #edcf8a;
    border-radius:11px;
    background:linear-gradient(145deg,#fffdf7,#fff4d8);
    color:#59431d;
    font-size:12px;
    line-height:1.5;
    font-weight:550;
    white-space:normal;
    overflow-wrap:anywhere;
    box-shadow:0 12px 32px rgba(71,52,18,.22);
    pointer-events:none;
    opacity:0;
    transform:translateY(7px) scale(.97);
    transition:opacity .18s ease,transform .18s ease;
    
}
.schedule-note-tooltip.is-visible  {
     opacity:1;
    transform:translateY(0) scale(1);
    
}
.schedule-note-tooltip::before  {
     content:'КОММЕНТАРИЙ';
    display:block;
    margin-bottom:4px;
    color:#a06a12;
    font-size:9px;
    font-weight:900;
    letter-spacing:.08em;
    
}
.employee-row-avatar  {
     width:27px;
    height:27px;
    border-radius:50%;
    object-fit:cover;
    flex:0 0 27px;
    border:1px solid #e1e7ef;
    background:#f1f5f9;
    box-shadow:0 1px 3px rgba(15,23,42,.08);
    
}
.employee-row-avatar.fallback  {
     display:inline-flex;
    align-items:center;
    justify-content:center;
    color:#66529a;
    font-size:11px;
    font-weight:800;
    
}
/* Аватар-контейнер показывает первую букву до полной загрузки фотографии. */
.avatar-image-frame {
    position:relative;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    flex:0 0 auto;
    vertical-align:middle;
    overflow:hidden;
    border-radius:50%;
    background:#e2e8f0;
}
.avatar-image-frame.status-avatar-frame { width:32px; height:32px; flex-basis:32px; }
.avatar-image-frame.employee-row-avatar-frame { width:27px; height:27px; flex-basis:27px; }
.avatar-image-frame.employee-option-avatar-frame { width:30px; height:30px; flex-basis:30px; }
.avatar-image-frame.popup-avatar-frame { width:40px; height:40px; flex-basis:40px; }
.avatar-image-placeholder {
    position:absolute !important;
    inset:0;
    z-index:1;
    display:flex !important;
    align-items:center !important;
    justify-content:center !important;
    width:100% !important;
    height:100% !important;
    margin:0 !important;
    padding:0 !important;
    border:1px solid #cbd5e1;
    border-radius:50%;
    background:#e2e8f0;
    color:#475569;
    font-size:12px;
    font-weight:800;
    line-height:1;
    opacity:1;
    visibility:visible;
    transition:opacity .16s ease,visibility .16s ease;
    object-fit:initial;
}
.status-avatar-frame .avatar-image-placeholder { font-size:13px; }
.employee-row-avatar-frame .avatar-image-placeholder { background:#f1f5f9; color:#66529a; border-color:#e1e7ef; font-size:11px; }
.employee-option-avatar-frame .avatar-image-placeholder { background:#f1f5f9; color:#67509d; border-color:#e2e8f0; font-size:12px; }
.popup-avatar-frame .avatar-image-placeholder { background:#f1f5f9; color:#475569; border-color:#e2e8f0; font-size:16px; }
.avatar-image-frame img.avatar-image-source {
    position:absolute !important;
    inset:0 !important;
    z-index:2;
    display:block !important;
    width:100% !important;
    height:100% !important;
    max-width:none;
    max-height:none;
    margin:0 !important;
    padding:0 !important;
    object-fit:cover;
    opacity:0;
    border-radius:50%;
    transition:opacity .16s ease;
}
.avatar-image-frame.is-loaded img.avatar-image-source { opacity:1; }
.avatar-image-frame.is-loaded .avatar-image-placeholder { opacity:0; visibility:hidden; }
.avatar-image-frame.has-error img.avatar-image-source { opacity:0; }
.avatar-image-frame.avatar-modal-frame {
    width:112px;
    height:112px;
    flex:0 0 auto;
    background:#e2e8f0;
}
.avatar-image-frame.avatar-modal-frame .avatar-image-placeholder { font-size:42px; }
.avatar-image-frame.avatar-modal-frame.is-loaded {
    width:auto;
    height:auto;
    overflow:visible;
    border-radius:8px;
    background:transparent;
}
.avatar-image-frame.avatar-modal-frame img.avatar-image-source {
    position:relative !important;
    inset:auto !important;
    width:auto !important;
    height:auto !important;
    max-width:90vw !important;
    max-height:90vh !important;
    object-fit:contain;
    border-radius:8px;
}
.popup-filial-preferences  {
     display:flex;
    flex-direction:column;
    gap:8px;
    
}
.popup-filial-preference-row  {
     display:flex;
    flex-direction:column;
    gap:6px;
    padding:8px;
    border:1px solid #e6e2ef;
    border-radius:9px;
    background:#fbfaff;
    animation:prefRowIn .2s ease-out both;
    
}
@keyframes prefRowIn  {
     from  {
         opacity:0;
        transform:translateY(4px);
        
    }
to  {
         opacity:1;
        transform:translateY(0);
        
    }
}
.popup-filial-preference-head  {
     display:flex;
    gap:6px;
    align-items:center;
    
}
.popup-filial-preference-head select  {
     flex:1;
    min-width:0;
    
}
.popup-remove-filial  {
     border:0;
    border-radius:6px;
    width:28px;
    height:28px;
    color:#b42318;
    background:#fff0ef;
    cursor:pointer;
    
}
.popup-preference-days  {
     display:flex;
    flex-wrap:wrap;
    gap:4px;
    
}
.pref-day-pill  {
     border:1px solid #e1dcef;
    border-radius:6px;
    padding:4px 6px;
    background:#fff;
    color:#667085;
    font-size:10px;
    font-weight:800;
    cursor:pointer;
    
}
.pref-day-pill.active  {
     background:#ede9fe;
    color:#6d46bd;
    border-color:#b9a6e7;
    box-shadow:inset 0 0 0 1px rgba(124,58,237,.08);
    
}
.popup-add-filial  {
     margin-top:3px;
    width:100%;
    border:1px dashed #b9acd7;
    border-radius:7px;
    background:#faf8ff;
    color:#6e52aa;
    padding:6px;
    font-size:11px;
    font-weight:700;
    cursor:pointer;
}
.popup-preference-hint  {
     color:#788399;
    font-size:10px;
    line-height:1.35;
    
}
.edit-comment-btn  {
     display:none;
    position:absolute;
    right:4px;
    bottom:3px;
    z-index:5;
    width:18px;
    height:18px;
    padding:0;
    align-items:center;
    justify-content:center;
    border:1px solid #d8d2e8;
    border-radius:5px;
    background:#fff;
    color:#7655c7;
    font-size:11px;
    line-height:1;
    cursor:pointer;
    opacity:.72;
    
}
.schedule-page.is-editing td.filial-day:hover .edit-comment-btn  {
     display:inline-flex;
    
}
.schedule-page.is-editing .edit-comment-btn:hover  {
     opacity:1;
    background:#f2edff;
    
}
.schedule-page.is-editing td.filial-day  {
     padding-bottom:6px;
    
}
.schedule-page.is-editing td.filial-day:hover  {
     border-color:#d5c8f2;
    
}
.schedule-page.is-editing td.saving-cell  {
     opacity:.65;
    pointer-events:none;
    
}
.search-dropdown  {
     position:fixed;
    background:#fff;
    border:1px solid var(--line-2);
    border-radius:9px;
    box-shadow:0 12px 28px rgba(15,23,42,.18);
    max-height:300px;
    overflow-y:auto;
    z-index:100000;
    padding:4px;
    margin:0;
    list-style:none;
    width:290px;
    
}
.search-dropdown li  {
     display:block;
    padding:8px 10px;
    cursor:pointer;
    font-size:12px;
    border:0;
    border-radius:7px;
    border-bottom:1px solid #f1f0f5;
    white-space:normal;
    
}
.search-dropdown li:hover  {
     background:#f6f3ff;
    
}
.search-dropdown li.disabled  {
     color:#94a3b8;
    cursor:not-allowed;
    background:#f8fafc;
    
}
.employee-option  {
     display:flex !important;
    align-items:flex-start;
    gap:9px;
    
}
.employee-option-avatar  {
     width:30px;
    height:30px;
    border-radius:50%;
    flex:0 0 auto;
    object-fit:cover;
    background:#f1f5f9;
    border:1px solid #e2e8f0;
    
}
.employee-option-avatar-fallback  {
     display:inline-flex;
    align-items:center;
    justify-content:center;
    color:#67509d;
    font-weight:800;
    font-size:12px;
    
}
.employee-option-content  {
     min-width:0;
    flex:1;
    
}
.employee-option-name  {
     font-weight:750;
    color:#1e293b;
    line-height:1.25;
    
}
.employee-option-meta  {
     display:block;
    margin-top:3px;
    color:#64748b;
    font-size:10.5px;
    line-height:1.35;
    
}
.employee-option-busy  {
     color:#b45309;
    font-weight:750;
    
}
.schedule-modal  {
     display:none;
    position:fixed;
    inset:0;
    z-index:100010;
    align-items:center;
    justify-content:center;
    padding:16px;
    background:rgba(15,23,42,.42);
    backdrop-filter:blur(2px);
    
}
.schedule-modal.active  {
     display:flex;
    animation:modalBackdropIn .18s ease-out both;
    
}
.schedule-modal-card  {
     width:min(520px,96vw);
    max-height:84vh;
    overflow:auto;
    padding:17px;
    border:1px solid #e5e1ef;
    border-radius:14px;
    background:#fff;
    box-shadow:0 22px 60px rgba(15,23,42,.22);
    animation:modalCardIn .22s cubic-bezier(.2,.8,.2,1) both;
    
}
.schedule-modal-title  {
     margin:0 0 12px;
    color:#1e293b;
    font-size:15px;
    font-weight:800;
    
}
.schedule-modal textarea  {
     width:100%;
    min-height:110px;
    resize:vertical;
    padding:10px;
    border:1px solid #d8d5e2;
    border-radius:8px;
    font:inherit;
    color:#1e293b;
    
}
.schedule-modal textarea:focus  {
     outline:0;
    border-color:var(--primary);
    box-shadow:0 0 0 3px rgba(118,85,199,.13);
    
}
.schedule-modal-actions  {
     display:flex;
    justify-content:flex-end;
    gap:8px;
    margin-top:12px;
    
}
#saveScheduleComment  {
     display:inline-flex !important;
    visibility:visible !important;
    opacity:1 !important;
    align-items:center;
    justify-content:center;
    min-width:116px;
    min-height:36px;
    background:#7655c7;
    color:#fff;
    border:0;
    position:relative;
    z-index:2;
    
}
#saveScheduleComment:hover  {
     background:#6443b4;
    
}
#saveScheduleComment:disabled  {
     opacity:.7 !important;
    cursor:wait;
    
}
.backup-list  {
     display:flex;
    flex-direction:column;
    gap:8px;
    
}
.backup-list-item  {
     display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    padding:10px;
    border:1px solid #ece9f3;
    border-radius:9px;
    background:#fcfbfe;
    
}
.backup-list-item strong  {
     display:block;
    color:#243047;
    font-size:12px;
    
}
.backup-list-item small  {
     display:block;
    margin-top:3px;
    color:#738096;
    font-size:10.5px;
    
}
.backup-actions  {
     display:flex;
    flex-wrap:wrap;
    gap:6px;
    align-items:center;
    
}
.delete-schedule-backup  {
     color:#b42318;
    border-color:#f0c8c5;
    background:#fff7f6;
    
}
.delete-all-backups-btn  {
     color:#fff;
    border-color:#a61b1b;
    background:#b42318;
    font-weight:800;
    
}
.delete-all-backups-btn:hover  {
     color:#fff;
    background:#991b1b;
    border-color:#991b1b;
    
}
.backup-empty  {
     padding:18px 8px;
    color:#718096;
    text-align:center;
    font-size:12px;
    
}
@keyframes modalBackdropIn  {
     from  {
         opacity:0;
        
    }
to  {
         opacity:1;
        
    }
}
@keyframes modalCardIn  {
     from  {
         opacity:0;
        transform:translateY(9px) scale(.985);
        
    }
to  {
         opacity:1;
        transform:translateY(0) scale(1);
        
    }
}
@media (prefers-reduced-motion:reduce)  {
     .schedule-page *, .schedule-page *::before, .schedule-page *::after  {
         animation-duration:.01ms !important;
        transition-duration:.01ms !important;
        
    }
}
.search-dropdown  {
     position:fixed;
    background:#fff;
    border:1px solid var(--line-2);
    border-radius:6px;
    box-shadow:0 4px 12px rgba(0,0,0,.15);
    max-height:200px;
    overflow-y:auto;
    z-index:100000;
    padding:0;
    margin:0;
    list-style:none;
    width:200px;
    
}
.search-dropdown li  {
     padding:8px 12px;
    cursor:pointer;
    font-size:12px;
    border-bottom:1px solid var(--line);
    
}
.search-dropdown li:hover  {
     background:#f1f5f9;
    
}
.search-dropdown li.disabled  {
     color:#94a3b8;
    cursor:not-allowed;
    background:#f8fafc;
    
}
@media (max-width:900px)  {
    
    .filials-layout  {
         flex-direction:column;
        align-items:stretch;
        
    }
.filials-main  {
         flex:1 1 auto;
        width:100%;
        
    }
.rest-main  {
         flex:1 1 auto;
        width:100%;
        max-width:none;
        min-width:0;
        display:block;
        max-height:none;
        
    }
.rest-main .card  {
         display:block;
        flex:none;
        height:auto !important;
        
    }
.status-container  {
         height:auto;
        max-height:420px;
        flex:none;
        overflow-y:auto;
        
    }
}
@media (max-width:900px)  {
    
    .schedule-page  {
         --role-w:82px;
        --emp-w:112px;
        --dept-day-w:34px;
        --branch-w:132px;
        --filial-day-w:56px;
        padding:14px 8px 36px;
        font-size:13px;
        
    }
.schedule-table  {
         font-size:11px;
        
    }
.schedule-table th, .schedule-table td  {
         padding:5px 3px;
        
    }
.schedule-table .col-role  {
         padding-left:7px;
        font-size:10px;
        
    }
.schedule-table .col-emp, .schedule-table .col-branch  {
         padding-left:7px;
        
    }
.emp-name, .filial-name  {
         font-size:11px;
        
    }
.call-mini  {
         width:18px;
        height:18px;
        
    }
.call-mini svg  {
         width:11px;
        height:11px;
        
    }
.d-dt  {
         font-size:11px;
        
    }
.d-wd  {
         font-size:9px;
        
    }
.controls-card  {
         flex-direction:column;
        align-items:stretch;
        gap:9px;
        padding:10px;
        
    }
.control-group  {
         width:100%;
        
    }
.control-group select  {
         flex:1 1 auto;
        min-width:0;
        
    }
.control-group .btn-primary-x  {
         flex:1 1 100%;
        
    }
.mode-btn  {
         flex:1 1 auto;
        
    }
}
@media (max-width:480px)  {
    
    .schedule-page  {
         --role-w:68px;
        --emp-w:94px;
        --dept-day-w:30px;
        --branch-w:108px;
        --filial-day-w:50px;
        
    }
.schedule-table  {
         font-size:10px;
        
    }
.day-check  {
         width:18px;
        height:18px;
        
    }
.day-check svg  {
         width:10px;
        height:10px;
        
    }
.emp-name, .filial-name  {
         font-size:10px;
        
    }
.section-title  {
         font-size:13px;
        
    }
}
.schedule-page form[method="GET"] button[type="submit"]:not(.mode-btn)  {
     background:var(--accent,var(--primary));
    color:#fff;
    border:none;
    padding:9px 24px;
    border-radius:8px;
    font-weight:600;
    font-size:14px;
    cursor:pointer;
    transition:background .2s,transform .18s,box-shadow .18s;
    
}
.schedule-page form[method="GET"] button[type="submit"]:not(.mode-btn):hover  {
     background:var(--accent-hover,var(--primary-hover));
    transform:translateY(-1px);
    box-shadow:0 5px 14px rgba(74,54,130,.16);
    
}
.schedule-page .btn-primary-x  {
     background:var(--accent,var(--primary));
    color:#fff;
    border:none;
    padding:9px 24px;
    border-radius:8px;
    font-weight:600;
    font-size:14px;
    cursor:pointer;
    transition:background .2s,transform .18s,box-shadow .18s;
    
}
.schedule-page .btn-primary-x:hover  {
     background:var(--accent-hover,var(--primary-hover));
    transform:translateY(-1px);
    box-shadow:0 5px 14px rgba(74,54,130,.16);
    
}
.dept-header-actions  {
     display:flex;
    align-items:center;
    gap:8px;
    
}
.dept-add-row-btn, .dept-row-edit-btn, .dept-row-delete-btn  {
     border:1px solid #ded9eb;
    border-radius:7px;
    background:#fff;
    color:#5f4b91;
    padding:5px 9px;
    font-size:11px;
    font-weight:700;
    cursor:pointer;
    transition:all .16s ease;
    
}
.dept-add-row-btn:hover, .dept-row-edit-btn:hover  {
     background:#f4f0ff;
    border-color:#b9a9df;
    
}
.dept-row-delete-btn  {
     color:#a52c2c;
    border-color:#f0d0ce;
    background:#fffafa;
    
}
.dept-row-delete-btn:hover  {
     background:#fff0ef;
    border-color:#e4a6a2;
    
}
.dept-actions-col, .dept-row-actions  {
     width:112px;
    min-width:112px;
    
}
.dept-row-actions  {
     white-space:nowrap;
    
}
.dept-row-actions button + button  {
     margin-left:4px;
    
}
#department-row-modal .dept-modal-fields  {
     display:grid;
    gap:10px;
    
}
#department-row-modal label  {
     display:block;
    color:#596579;
    font-size:12px;
    font-weight:700;
    margin-bottom:4px;
    
}
#department-row-modal input, #department-row-modal select  {
     width:100%;
    min-height:38px;
    border:1px solid #d9dce7;
    border-radius:8px;
    padding:7px 10px;
    background:#fff;
    color:#243047;
    
}
#department-row-modal input:focus, #department-row-modal select:focus  {
     outline:0;
    border-color:var(--primary);
    box-shadow:0 0 0 3px rgba(118,85,199,.12);
    
}
#schedule-toast  {
     position:fixed;
    top:18px;
    right:18px;
    z-index:100050;
    max-width:min(380px,calc(100vw - 36px));
    padding:11px 16px;
    border-radius:10px;
    color:#fff;
    font-size:13px;
    font-weight:650;
    box-shadow:0 9px 28px rgba(20,25,45,.2);
    opacity:0;
    transform:translateY(-8px);
    pointer-events:none;
    transition:opacity .2s ease,transform .2s ease;
    
}
#schedule-toast.is-visible  {
     opacity:1;
    transform:translateY(0);
    
}
#schedule-toast.is-error  {
     background:#b93838;
    
}
#schedule-toast.is-success  {
     background:#21845d;
    
}
.schedule-page.design-modern  {
     --bg:#f5f6fc;
    --card:#fff;
    --line:#e9eaf3;
    --line-2:#d9dced;
    --primary:#7054c3;
    --primary-hover:#5f43ae;
    background:radial-gradient(ellipse at 12% 0%,rgba(154,132,220,.10),transparent 38%),linear-gradient(180deg,#f7f8fd 0%,#f2f4fa 100%);
    
}
.design-modern .controls-card, .design-modern .card, .design-modern .birthdays-card, .design-modern .quitting-card  {
     border-radius:15px;
    border-color:rgba(220,222,237,.9);
    box-shadow:0 3px 12px rgba(44,39,82,.055),0 1px 2px rgba(44,39,82,.035);
    transition:box-shadow .22s ease,transform .22s ease,border-color .22s ease;
    
}
.design-modern .card:hover, .design-modern .controls-card:hover  {
     box-shadow:0 9px 24px rgba(59,48,111,.085),0 0 0 1px rgba(130,108,196,.045);
    border-color:#d9d2ed;
    
}
.design-modern .card-header-x  {
     background:linear-gradient(180deg,#fff 0%,#f7f7fc 100%);
    border-bottom-color:#ecebf4;
    border-radius:15px 15px 0 0;
    padding-top:11px;
    padding-bottom:11px;
    
}
.design-modern .controls-card  {
     padding:13px 16px;
    background:rgba(255,255,255,.92);
    backdrop-filter:blur(8px);
    
}
.design-modern .mode-btn  {
     box-shadow:0 1px 4px rgba(35,29,72,.07);
    transition:all .18s ease;
    
}
.design-modern .mode-btn:hover  {
     box-shadow:0 5px 13px rgba(59,48,111,.12);
    
}
.design-modern .mode-btn.active  {
     box-shadow:0 4px 10px rgba(112,84,195,.20);
    
}
.design-modern .control-group select, .design-modern .mode-btn, .design-modern .btn-primary-x, .design-modern .dept-add-row-btn, .design-modern .dept-row-edit-btn, .design-modern .dept-row-delete-btn  {
     border-radius:9px;
    
}
.design-modern .btn-primary-x, .design-modern form[method="GET"] button[type="submit"]:not(.mode-btn)  {
     box-shadow:0 3px 9px rgba(91,68,158,.16);
    
}
.design-modern .schedule-table th  {
     background:#f8f8fd;
    
}
.design-modern .schedule-table tbody tr:hover td  {
     background-color:#fbfaff;
    
}
.design-modern .schedule-table .col-role, .design-modern .schedule-table .col-emp, .design-modern .schedule-table .col-branch  {
     background:#fff;
    
}
.design-modern .section-bar  {
     box-shadow:0 0 8px rgba(124,90,201,.24);
    
}
.design-modern .editing-banner  {
     border-radius:12px;
    box-shadow:0 3px 12px rgba(67,54,113,.07);
    
}
.design-modern .legend-item  {
     border-radius:999px;
    box-shadow:0 2px 7px rgba(37,30,70,.06);
    
}
.design-modern .schedule-note-indicator  {
     box-shadow:0 2px 8px rgba(245,158,11,.22);
    
}
.design-modern .day-check  {
     box-shadow:0 2px 8px rgba(34,197,94,.18);
    
}
@media (prefers-reduced-motion:reduce)  {
     .design-modern *, .design-modern *::before, .design-modern *::after  {
         animation-duration:.01ms !important;
        transition-duration:.01ms !important;
        
    }
}
#department-row-modal .department-modal-actions  {
     display:flex !important;
    visibility:visible !important;
    opacity:1 !important;
    align-items:center !important;
    justify-content:flex-end !important;
    flex-direction:row !important;
    flex-wrap:nowrap !important;
    gap:10px !important;
    min-height:46px;
    margin-top:16px;
    width:100%;
    position:relative;
    z-index:5;
    
}
#department-row-modal #cancelDepartmentRow  {
     display:inline-flex !important;
    visibility:visible !important;
    opacity:1 !important;
    align-items:center !important;
    justify-content:center !important;
    min-height:40px;
    position:static !important;
    flex:0 0 auto !important;
    margin:0 !important;
    
}
#department-row-modal #saveDepartmentRow  {
     display:inline-flex !important;
    visibility:visible !important;
    opacity:1 !important;
    align-items:center !important;
    justify-content:center !important;
    min-width:124px !important;
    min-height:40px !important;
    padding:9px 24px !important;
    border:0 !important;
    border-radius:9px !important;
    background:var(--accent,var(--primary)) !important;
    color:#fff !important;
    font-weight:700 !important;
    font-size:14px !important;
    position:static !important;
    flex:0 0 auto !important;
    margin:0 !important;
    clip:auto !important;
    clip-path:none !important;
    transform:none;
    z-index:6 !important;
    pointer-events:auto !important;
    white-space:nowrap !important;
    
}
#department-row-modal #saveDepartmentRow:hover  {
     filter:brightness(.95);
    box-shadow:0 5px 14px rgba(75,56,135,.20);
    
}
#department-row-modal #saveDepartmentRow:disabled  {
     opacity:.75 !important;
    cursor:wait !important;
    
}
#department-row-modal .schedule-modal-card  {
     padding-bottom:20px;
    
}
.schedule-modal-actions  {
     align-items:center;
    flex-wrap:wrap;
    
}
.schedule-page.design-modern  {
     --bg:#f1f4fb;
    --card:#fff;
    --text:#17223a;
    --muted:#69758d;
    --line:#e5eaf3;
    --line-2:#d4dceb;
    --primary:#635bdb;
    --primary-hover:#5148c7;
    background:radial-gradient(ellipse at 8% 0%,rgba(99,91,219,.16),transparent 34%),radial-gradient(ellipse at 95% 12%,rgba(20,184,166,.10),transparent 30%),linear-gradient(180deg,#f7f9fd 0%,#eef2f9 100%);
    color:#17223a;
    
}
.design-modern .schedule-title-block h1  {
     color:#17223a;
    letter-spacing:-.035em;
    font-size:clamp(22px,3vw,30px);
    
}
.design-modern .schedule-subtitle  {
     color:#73809a;
    font-size:13px;
    
}
.design-modern .schedule-topbar  {
     margin-bottom:18px;
    
}
.design-modern .controls-card  {
     background:rgba(255,255,255,.88);
    backdrop-filter:blur(14px);
    border:1px solid rgba(216,224,240,.9);
    border-radius:18px;
    box-shadow:0 10px 28px rgba(36,49,87,.07),0 2px 6px rgba(36,49,87,.035);
    padding:15px 18px;
    
}
.design-modern .control-group select  {
     min-height:38px;
    border-radius:10px;
    border:1px solid #dbe2f0;
    background-color:#fff;
    padding:7px 30px 7px 11px;
    font-size:13px;
    
}
.design-modern .control-group select:focus  {
     border-color:#8b83ee;
    box-shadow:0 0 0 4px rgba(99,91,219,.13);
    
}
.design-modern .card, .design-modern .birthdays-card, .design-modern .quitting-card  {
     background:rgba(255,255,255,.96);
    border:1px solid #e2e8f3;
    border-radius:18px;
    box-shadow:0 8px 24px rgba(31,45,85,.055),0 1px 3px rgba(31,45,85,.035);
    overflow:hidden;
    
}
.design-modern .card:hover  {
     border-color:#d5d9f4;
    box-shadow:0 14px 32px rgba(39,48,103,.09),0 0 0 1px rgba(99,91,219,.045);
    transform:translateY(-1px);
    
}
.design-modern .card-header-x  {
     background:linear-gradient(115deg,#fff 0%,#f5f6ff 72%,#f0fbfa 100%);
    border-bottom:1px solid #e8ebf5;
    padding:13px 17px;
    
}
.design-modern .card-header-x h3  {
     font-size:14px;
    letter-spacing:-.01em;
    color:#24304b;
    
}
.design-modern .section-title  {
     color:#25314c;
    font-size:16px;
    font-weight:850;
    margin:25px 0 12px;
    
}
.design-modern .section-bar  {
     width:5px;
    height:22px;
    border-radius:9px;
    background:linear-gradient(180deg,#8179ed,#36b8aa);
    box-shadow:0 0 12px rgba(99,91,219,.28);
    
}
.design-modern .legend-item  {
     background:rgba(255,255,255,.88);
    border:1px solid #e2e7f1;
    border-radius:999px;
    padding:5px 10px;
    box-shadow:0 2px 8px rgba(35,48,85,.045);
    
}
.design-modern .schedule-table  {
     border-spacing:0;
    font-size:12px;
    
}
.design-modern .schedule-table th  {
     background:linear-gradient(180deg,#f8f9ff,#f0f3fa);
    color:#66728b;
    border-color:#e5eaf3;
    font-size:10px;
    letter-spacing:.055em;
    
}
.design-modern .schedule-table td  {
     border-color:#e9edf5;
    transition:background .16s ease,box-shadow .16s ease;
    
}
.design-modern .schedule-table tbody tr:hover td  {
     background:#f7f8ff;
    
}
.design-modern .schedule-table .col-role  {
     color:#69758d;
    font-weight:700;
    
}
.design-modern .schedule-table .col-emp, .design-modern .schedule-table .col-branch  {
     color:#202c47;
    font-weight:700;
    
}
.design-modern .schedule-table .col-role, .design-modern .schedule-table .col-emp, .design-modern .schedule-table .col-branch  {
     box-shadow:7px 0 13px -11px rgba(28,39,76,.4);
    
}
.design-modern .day-cell, .design-modern .filial-day  {
     position:relative;
    
}
.design-modern .day-cell:hover, .design-modern .filial-day:hover  {
     box-shadow:inset 0 0 0 1px rgba(99,91,219,.20);
    
}
.design-modern .day-check  {
     border-radius:8px;
    box-shadow:0 2px 7px rgba(22,163,74,.13);
    
}
.design-modern .day-check.checked  {
     background:linear-gradient(135deg,#21a879,#16a085);
    
}
.design-modern .schedule-note-indicator  {
     border:1px solid rgba(217,145,22,.35);
    box-shadow:0 3px 9px rgba(217,145,22,.18);
    
}
.design-modern .editing-banner  {
     background:linear-gradient(110deg,#f0edff,#effbfa);
    border:1px solid #dedafa;
    border-radius:14px;
    box-shadow:0 5px 16px rgba(77,70,160,.07);
    
}
.design-modern .mode-btn  {
     min-height:36px;
    border-radius:10px;
    border-color:#dce2ef;
    background:rgba(255,255,255,.95);
    color:#45516c;
    box-shadow:0 2px 5px rgba(30,44,80,.04);
    
}
.design-modern .mode-btn:hover  {
     border-color:#aaa5ed;
    background:#f7f6ff;
    color:#5148c7;
    box-shadow:0 7px 16px rgba(83,74,185,.12);
    
}
.design-modern .mode-btn.active  {
     color:#fff;
    background:linear-gradient(135deg,#7168e5,#5850c8);
    border-color:#625bd3;
    box-shadow:0 6px 15px rgba(91,81,201,.23);
    
}
.design-modern .btn-primary-x, .design-modern #department-row-modal #saveDepartmentRow  {
     min-height:40px;
    border-radius:10px;
    background:linear-gradient(135deg,#7168e5,#5850c8);
    box-shadow:0 5px 13px rgba(91,81,201,.20);
    font-weight:750;
    
}
.design-modern .btn-primary-x:hover, .design-modern #department-row-modal #saveDepartmentRow:hover  {
     filter:brightness(1.04);
    box-shadow:0 8px 18px rgba(91,81,201,.25);
    
}
.design-modern .dept-add-row-btn, .design-modern .dept-row-edit-btn, .design-modern .dept-row-delete-btn  {
     border-radius:9px;
    padding:7px 11px;
    
}
.design-modern .schedule-modal  {
     background:rgba(17,26,49,.48);
    backdrop-filter:blur(8px);
    
}
.design-modern .schedule-modal-card  {
     width:min(540px,96vw);
    padding:22px;
    border:1px solid rgba(255,255,255,.72);
    border-radius:20px;
    background:rgba(255,255,255,.98);
    box-shadow:0 28px 80px rgba(16,27,57,.28),0 4px 14px rgba(30,42,83,.10);
    
}
.design-modern .schedule-modal-title  {
     color:#1d2945;
    font-size:17px;
    letter-spacing:-.02em;
    
}
.design-modern #department-row-modal input, .design-modern #department-row-modal select, .design-modern .schedule-modal textarea  {
     border-radius:11px;
    border-color:#dce3f0;
    min-height:42px;
    
}
.design-modern #department-row-modal input:focus, .design-modern #department-row-modal select:focus, .design-modern .schedule-modal textarea:focus  {
     border-color:#8b83ee;
    box-shadow:0 0 0 4px rgba(99,91,219,.13);
    
}
.design-modern #schedule-toast  {
     border-radius:12px;
    box-shadow:0 14px 35px rgba(28,39,75,.22);
    
}
.design-modern .status-group  {
     border-radius:13px;
    border:1px solid #e7ebf4;
    background:#fff;
    
}
.design-modern .status-group-title  {
     color:#34415f;
    font-weight:850;
    
}
@media (max-width:760px)  {
     .design-modern .controls-card  {
         border-radius:14px;
        padding:11px;
        
    }
.design-modern .card  {
         border-radius:14px;
        
    }
.design-modern .schedule-modal-card  {
         padding:17px;
        border-radius:16px;
        
    }
}
#department-row-modal .department-modal-actions  {
     display:flex !important;
    align-items:center !important;
    justify-content:flex-end !important;
    flex-wrap:nowrap !important;
    gap:12px !important;
    min-height:48px !important;
    padding-top:6px !important;
    position:relative !important;
    z-index:20 !important;
    
}
#department-row-modal .department-modal-actions #saveDepartmentRow, #department-row-modal .department-modal-actions #saveDepartmentRow:disabled, #department-row-modal .department-modal-actions #saveDepartmentRow[disabled]  {
     display:inline-flex !important;
    visibility:visible !important;
    position:static !important;
    flex:0 0 auto !important;
    align-items:center !important;
    justify-content:center !important;
    width:auto !important;
    min-width:132px !important;
    min-height:42px !important;
    margin:0 !important;
    padding:10px 25px !important;
    border:0 !important;
    border-radius:10px !important;
    background:#635bdb !important;
    background-image:linear-gradient(135deg,#746be8,#554bc8) !important;
    color:#fff !important;
    -webkit-text-fill-color:#fff !important;
    font-size:14px !important;
    font-weight:800 !important;
    line-height:1.2 !important;
    opacity:1 !important;
    filter:none !important;
    text-shadow:0 1px 1px rgba(25,20,75,.16) !important;
    box-shadow:0 6px 16px rgba(91,81,201,.28) !important;
    clip:auto !important;
    clip-path:none !important;
    transform:none !important;
    pointer-events:auto !important;
    white-space:nowrap !important;
    cursor:pointer !important;
    
}
#department-row-modal .department-modal-actions #saveDepartmentRow:hover  {
     background-image:linear-gradient(135deg,#8179f0,#6056d8) !important;
    box-shadow:0 9px 20px rgba(91,81,201,.34) !important;
    transform:translateY(-1px) !important;
    
}
#department-row-modal .department-modal-actions #saveDepartmentRow:disabled  {
     cursor:wait !important;
    background-image:linear-gradient(135deg,#746be8,#554bc8) !important;
    
}
#department-row-modal .department-modal-actions #cancelDepartmentRow  {
     display:inline-flex !important;
    visibility:visible !important;
    opacity:1 !important;
    position:static !important;
    flex:0 0 auto !important;
    min-height:42px !important;
    
}
.employee-work-cell .filial-name  {
     display:inline-block;
    max-width:100%;
    
}
.schedule-date-field  {
     position:relative;
    flex:1 1 140px;
    min-width:0;
    width:100%;
    
}
.schedule-date-native  {
     display:none !important;
    
}
.schedule-date-trigger  {
     display:flex !important;
    align-items:center;
    gap:9px;
    width:100%;
    min-width:0;
    min-height:38px;
    padding:8px 11px !important;
    border:1px solid var(--line-2,#d7dbea) !important;
    border-radius:10px !important;
    background:linear-gradient(180deg,#fff,#f8faff) !important;
    color:var(--text,#243047) !important;
    text-align:left !important;
    font:inherit !important;
    font-size:12.5px !important;
    font-weight:600 !important;
    line-height:1.35 !important;
    box-shadow:0 2px 7px rgba(40,50,85,.045);
    cursor:pointer;
    transition:border-color .18s,box-shadow .18s,transform .18s;
    
}
.schedule-date-trigger:hover  {
     border-color:var(--primary,#7568df) !important;
    box-shadow:0 4px 12px rgba(91,81,201,.11);
    
}
.schedule-date-trigger:focus  {
     outline:0;
    border-color:var(--primary,#7568df) !important;
    box-shadow:0 0 0 3px rgba(117,104,223,.15);
    
}
.schedule-date-trigger:disabled  {
     opacity:.5;
    cursor:not-allowed;
    
}
.schedule-date-icon  {
     width:18px;
    height:18px;
    display:inline-flex;
    flex:0 0 18px;
    color:var(--primary,#7568df);
    
}
.schedule-date-label  {
     flex:1 1 auto;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
    
}
.schedule-date-placeholder  {
     color:#94a3b8;
    font-weight:500;
    
}
.schedule-date-chevron  {
     color:#8490a5;
    font-size:14px;
    
}
.schedule-calendar-popover  {
     position:fixed;
    z-index:100500;
    display:none;
    width:292px;
    max-width:calc(100vw - 20px);
    padding:13px;
    border:1px solid rgba(204,210,226,.95);
    border-radius:16px;
    background:rgba(255,255,255,.98);
    box-shadow:0 18px 48px rgba(30,41,75,.20),0 3px 10px rgba(30,41,75,.08);
    color:#202c43;
    backdrop-filter:blur(12px);
    animation:scheduleCalendarIn .16s ease-out;
    
}
@keyframes scheduleCalendarIn  {
     from  {
        opacity:0;
        transform:translateY(5px) scale(.985)
    }
to  {
        opacity:1;
        transform:translateY(0) scale(1)
    }
}
.schedule-calendar-head  {
     display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
    margin-bottom:11px;
    
}
.schedule-calendar-caption  {
     font-size:14px;
    font-weight:800;
    letter-spacing:-.01em;
    
}
.schedule-calendar-nav  {
     display:flex;
    align-items:center;
    gap:5px;
    
}
.schedule-calendar-nav button, .schedule-calendar-footer button  {
     border:0;
    border-radius:8px;
    background:#f1f3fa;
    color:#475569;
    min-width:30px;
    height:30px;
    padding:0 8px;
    font-weight:700;
    cursor:pointer;
    transition:background .15s,color .15s,transform .15s;
    
}
.schedule-calendar-nav button:hover, .schedule-calendar-footer button:hover  {
     background:#ebe8ff;
    color:#6558cb;
    
}
.schedule-calendar-weekdays, .schedule-calendar-grid  {
     display:grid;
    grid-template-columns:repeat(7,minmax(0,1fr));
    gap:4px;
    
}
.schedule-calendar-weekday  {
     text-align:center;
    color:#8993a5;
    font-size:10px;
    font-weight:800;
    padding:4px 0 7px;
    
}
.schedule-calendar-day, .schedule-calendar-empty  {
     min-width:0;
    height:32px;
    border-radius:9px;
    
}
.schedule-calendar-day  {
     border:0;
    background:transparent;
    color:#26334c;
    font-size:12px;
    font-weight:600;
    cursor:pointer;
    transition:background .13s,color .13s,transform .13s;
    
}
.schedule-calendar-day:hover  {
     background:#efedff;
    color:#5d50c8;
    transform:translateY(-1px);
    
}
.schedule-calendar-day.is-weekend  {
     color:#e05b67;
    
}
.schedule-calendar-day.is-today  {
     box-shadow:inset 0 0 0 1px #a89ff0;
    
}
.schedule-calendar-day.is-selected  {
     background:linear-gradient(135deg,#796bea,#5a4fc8);
    color:#fff;
    box-shadow:0 4px 10px rgba(91,81,201,.25);
    
}
.schedule-calendar-footer  {
     display:flex;
    align-items:center;
    justify-content:space-between;
    gap:8px;
    padding-top:11px;
    margin-top:9px;
    border-top:1px solid #edf0f6;
    
}
.schedule-calendar-footer button  {
     height:29px;
    font-size:11px;
    
}
.schedule-calendar-footer .schedule-calendar-today  {
     background:#f0edff;
    color:#6055ca;
    
}
@media (max-width:480px)  {
     .schedule-calendar-popover  {
         width:280px;
        
    }
.schedule-calendar-day  {
         height:35px;
        
    }
}
@media (min-width:901px)  {
    
    .filials-layout  {
         align-items:flex-start !important;
        
    }
.filials-main  {
         align-self:flex-start !important;
        
    }
.filials-main > .card  {
         height:auto !important;
        
    }
.rest-main  {
         align-self:flex-start !important;
        min-height:0 !important;
        overflow:hidden !important;
        
    }
.rest-main > .card  {
         min-height:0 !important;
        overflow:hidden !important;
        
    }
.rest-main .status-container  {
         min-height:0 !important;
        height:0;
        flex:1 1 0 !important;
        
    }
}
@media (max-width:900px)  {
    
    .rest-main  {
         height:auto !important;
        overflow:visible !important;
        
    }
.rest-main > .card  {
         height:auto !important;
        overflow:visible !important;
        
    }
.rest-main .status-container  {
         height:auto !important;
        max-height:420px !important;
        flex:none !important;
        
    }
}
@media (min-width:901px)  {
    
    .filials-layout  {
         align-items:flex-start !important;
        
    }
.filials-main  {
         flex:1 1 0 !important;
        min-width:0 !important;
        align-self:flex-start !important;
        
    }
.filials-main > .card  {
         height:auto !important;
        min-height:0 !important;
        
    }
.rest-main  {
         align-self:flex-start !important;
        flex:0 0 340px !important;
        min-height:0 !important;
        overflow:hidden !important;
        
    }
.rest-main > .card  {
         display:flex !important;
        flex-direction:column !important;
        height:100% !important;
        min-height:0 !important;
        max-height:100% !important;
        overflow:hidden !important;
        
    }
.rest-main .status-container  {
         flex:1 1 0 !important;
        height:0 !important;
        min-height:0 !important;
        overflow-x:hidden !important;
        overflow-y:auto !important;
        
    }
}
@media (max-width:900px)  {
    
    .filials-layout  {
         align-items:stretch !important;
        
    }
.rest-main  {
         width:100% !important;
        max-width:none !important;
        min-width:0 !important;
        height:auto !important;
        max-height:none !important;
        overflow:visible !important;
        
    }
.rest-main > .card  {
         display:block !important;
        height:auto !important;
        max-height:none !important;
        overflow:visible !important;
        
    }
.rest-main .status-container  {
         height:auto !important;
        max-height:420px !important;
        flex:none !important;
        overflow-y:auto !important;
        
    }
}
</style>


<div class="schedule-page <?= $isPrivileged ? 'is-editing' : '' ?> design-<?= CHtml::encode($designMode) ?>" id="schedulePage" data-year="<?= (int)$year ?>" data-month="<?= (int)$month ?>" data-view-type="<?= CHtml::encode($viewType) ?>" data-start-date="<?= CHtml::encode($startDate) ?>" data-today="<?= CHtml::encode($today) ?>" data-period-start="<?= !empty($days) ? CHtml::encode(reset($days)) : '' ?>" data-period-end="<?= !empty($days) ? CHtml::encode(end($days)) : '' ?>">
    <div class="schedule-topbar">
        <div class="schedule-title-block">
            <h1>График работ</h1>
            <div class="schedule-subtitle">
<?= $months[(int)$month] ?> <?= $year ?>
</div>
        </div>
        <div class="legend">
            <span class="legend-item">
<span class="legend-dot" style="background:#0ea5e9;">
</span>Открыта</span>
            <span class="legend-item">
<span class="legend-dot" style="background:#22c55e;">
</span>Зафиксирована</span>
            <span class="legend-item">
<span class="legend-dot" style="background:#ef4444;">
</span>Не зафиксирована</span>
            <span class="legend-item">
<span class="legend-dot" style="background:#fff;border:1px solid #cbd5e1;">
</span>Нет смены</span>
            <span class="legend-item">
<span class="legend-dot" style="background:#f59e0b;">
</span>Нет сотрудника</span>
            <span class="legend-item">
<span class="legend-dot" style="background:#7c3aed;">
</span>Сегодня</span>
            <span class="legend-item">
<span class="legend-note-symbol">!</span>Комментарий</span>
        </div>
    </div>

    <?php if ($isPrivileged): ?>
        <div class="editing-banner">
            <span class="editing-banner-dot">
</span>
            <strong>Режим редактирования включён</strong>
            <span>Изменения графика сохраняются автоматически</span>
        </div>
    <?php endif; ?>

    <?php if ($isMyMode && $currentEmployee): ?>
        <div class="my-banner">
<div>
<div class="my-banner-title">Мой график</div>
<div class="my-banner-sub">
<?= CHtml::encode($currentEmployee['name']) ?>
</div>
</div>
</div>
    <?php endif; ?>

    <div class="controls-card">
        <form method="GET" class="control-group" id="scheduleFilters">
            <span class="ctrl-label">Месяц:</span>
            <select name="month" aria-label="Месяц">
                <?php foreach ($months as $num => $name): ?>
                    <option value="<?= sprintf('%02d', $num) ?>" <?= ((int)$month === $num) ? 'selected' : '' ?>>
<?= $name ?>
</option>
                <?php endforeach; ?>
            </select>
            <span class="ctrl-label">Год:</span>
            <select name="year" aria-label="Год">
                <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                    <option value="<?= $y ?>" <?= ($y == $year) ? 'selected' : '' ?>>
<?= $y ?>
</option>
                <?php endfor; ?>
            </select>
            <span class="ctrl-label">Период:</span>
            <select name="view_type" aria-label="Период">
                <option value="day" <?= $viewType == 'day' ? 'selected' : '' ?>>Текущий день</option>
                <option value="week" <?= $viewType == 'week' ? 'selected' : '' ?>>Текущая неделя</option>
                <option value="month" <?= $viewType == 'month' ? 'selected' : '' ?>>Текущий месяц</option>
            </select>
            <input type="hidden" name="start_date" value="<?= CHtml::encode($startDate) ?>">
            <input type="hidden" name="mode" value="<?= CHtml::encode($mode) ?>">
        </form>
        <form method="get" class="control-group">
            <input type="hidden" name="year" value="<?= $year ?>">
            <input type="hidden" name="month" value="<?= $month ?>">
            <input type="hidden" name="view_type" value="<?= $viewType ?>">
            <input type="hidden" name="start_date" value="<?= CHtml::encode($startDate) ?>">
            <button type="submit" name="mode" value="all" class="mode-btn <?= Yii::app()->request->getParam('mode', 'all') == 'all' ? 'active' : '' ?>">Весь график</button>
            <button type="submit" name="mode" value="my" class="mode-btn <?= Yii::app()->request->getParam('mode') == 'my' ? 'active' : '' ?>">Мой график</button>
        </form>
        <?php if ($isPrivileged): ?>
            <div class="copy-btn-wrap edit-only schedule-edit-actions">
                <button type="button" id="copyPrevMonthBtn" class="mode-btn" style="background:#4f8bcb;color:#fff;border:none;">Скопировать прошлый месяц</button>
                <button type="button" id="autoFillScheduleBtn" class="mode-btn" style="background:#168a62;color:#fff;border:none;">Автозаполнить по предпочтениям</button>
                <button type="button" id="clearSchedulePeriodBtn" class="mode-btn" style="background:#c24141;color:#fff;border:none;">Очистить выбранный период</button>
                <button type="button" id="showBackupHistoryBtn" class="mode-btn" style="background:#7655c7;color:#fff;border:none;">История и восстановление</button>
            </div>
        <?php endif; ?>
    </div>

    <?php
    $currentMonth = date('m');
    $birthdays = Yii::app()->db->createCommand()->select('name, birthday')->from('service_employee')->where('MONTH(birthday) = :month AND active = 1', array(':month' => $currentMonth))->order('DAY(birthday) ASC')->queryAll();
    ?>
    <div class="birthdays-card">
        <h3>Дни рождения в этом месяце</h3>
        <?php if (!empty($birthdays)): ?>
            <ul class="birthdays-list">
                <?php foreach ($birthdays as $b): ?>
<li>
<?= CHtml::encode($b['name']) ?> — <?= date('d.m', strtotime($b['birthday'])) ?>
</li>
<?php endforeach; ?>
            </ul>
        <?php else: ?>
<p class="birthdays-empty">В этом месяце дней рождения нет.</p>
<?php endif; ?>
    </div>

    <?php if ($isAdmin && !empty($quittingEmployees)): ?>
        <div class="quitting-card">
            <h3>Планируют уволиться</h3>
            <ul class="birthdays-list">
                <?php foreach ($quittingEmployees as $q): ?>
                    <li>
<?= CHtml::encode($q['name']) ?> — <?= $q['date'] ? 'до ' . date('d.m.Y', strtotime($q['date'])) : 'дата не указана' ?>
</li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php
    $filialsToShow = $filials;
    if ($isMyMode) {
        $filialsToShow = array();
        foreach ($filials as $fid => $fname) {
            $fidDays = isset($schedule['filial'][$fid]) ? $schedule['filial'][$fid] : array();
            foreach ($fidDays as $empIds) {
                if (in_array($currentEmployeeId, (array)$empIds)) { $filialsToShow[$fid] = $fname; break; }
            }
            if (isset($scheduleComments[$fid]) && !empty($scheduleComments[$fid])) $filialsToShow[$fid] = $fname;
        }
    }
    ?>

    <?php if (!empty($filialsToShow)): ?>
        <div class="section-title">
<span class="section-bar">
</span>Филиалы</div>
        <div class="filials-layout">
            <div class="filials-main">
                <div class="card">
                    <div class="card-header-x">
<h3>Распределение сотрудников по филиалам</h3>
<span class="card-badge">
<?= count($filialsToShow) ?> фил.</span>
</div>
                    <div class="table-scroll">
                        <table class="schedule-table filial-table">
                            <thead>
<tr>
                                <th class="col-branch">Филиал</th>
                                <?php foreach ($days as $d): ?>
                                    <?php $w = date('w', strtotime($d)); $isWeekend = ($w == 0 || $w == 6); $isToday = ($d === $today); ?>
                                    <th class="day-head <?= $isWeekend ? 'weekend-head' : '' ?> <?= $isToday ? 'today-head' : '' ?>" data-date="<?= CHtml::encode($d) ?>">
<div class="d-wd">
<?= $weekdays[$w] ?>
</div>
<div class="d-dt">
<?= date('d', strtotime($d)) ?>
</div>
</th>
                                <?php endforeach; ?>
                            </tr>
</thead>
                            <tbody>
                                <?php foreach ($filialsToShow as $fid => $fname): ?>
                                    <?php $filialPhone = isset($filialPhones[$fid]) ? trim((string)$filialPhones[$fid]) : ''; ?>
                                    <tr>
                                        <td class="col-branch" title="<?= CHtml::encode($fname) ?>">
<div class="emp-cell">
                                            <?php if (!$isMyMode && $filialPhone !== ''): ?>
<a href="#" class="call-mini call-btn" data-phone="<?= CHtml::encode($filialPhone) ?>" title="Позвонить в филиал: <?= CHtml::encode($filialPhone) ?>">
<?= $phoneSvg ?>
</a>
<?php endif; ?>
                                            <span class="emp-name" title="<?= CHtml::encode($fname) ?>">
<?= CHtml::encode($fname) ?>
</span>
                                        </div>
</td>
                                        <?php foreach ($days as $d):
                                            $w = date('w', strtotime($d));
                                            $isWeekend = ($w == 0 || $w == 6);
                                            $isToday = ($d === $today);
                                            $todayClass = $isToday ? 'today-cell' : '';
                                            if ($isMyMode) {
                                                $worksHere = isset($schedule['filial'][$fid][$d]) && is_array($schedule['filial'][$fid][$d]) && in_array($currentEmployeeId, $schedule['filial'][$fid][$d]);
                                                $myComment = isset($scheduleComments[$fid][$d]) ? $scheduleComments[$fid][$d] : '';
                                                if (!$worksHere) {
                                                    echo '<td class="filial-day no-shift '.($isWeekend?'weekend-cell':'').' '.$todayClass.'">';
                                                    echo '<span class="no-shift-mark">—</span>';
                                                    if ($myComment !== '') echo '<span class="schedule-note-indicator" tabindex="0" aria-label="Комментарий к ячейке" data-note="'.CHtml::encode($myComment).'">!</span>';
                                                    echo '</td>'; continue;
                                                }
                                                $statusClass = '';
                                                if (isset($shiftStatus[$fid][$d][$currentEmployeeId])) {
                                                    $st = $shiftStatus[$fid][$d][$currentEmployeeId];
                                                    if ($st == 'open') $statusClass = 'shift-open'; elseif ($st == 'closed') $statusClass = 'shift-closed'; elseif ($st == 'not_closed') $statusClass = 'shift-error';
                                                }
                                                echo '<td class="filial-day '.$statusClass.' '.($isWeekend?'weekend-cell':'').' '.$todayClass.'" title="Я работаю здесь">
<span class="filial-name">✓ Я</span>';
                                                if ($myComment !== '') echo '<span class="schedule-note-indicator" tabindex="0" aria-label="Комментарий к ячейке" data-note="'.CHtml::encode($myComment).'">!</span>';
                                                echo '</td>'; continue;
                                            }
                                            $empId = isset($schedule['filial'][$fid][$d][0]) ? $schedule['filial'][$fid][$d][0] : '';
                                            $emptyClass = $empId ? '' : 'empty-cell';
                                            $nameHtml = ''; $plainName = '';
                                            $triggerClass = ''; $triggerAttrs = '';
                                            if ($empId && isset($employees[$empId])) {
                                                $plainName = $employees[$empId]['name'];
                                                $nameHtml = $employees[$empId]['active'] == 0 ? '<s>'.CHtml::encode($employees[$empId]['name']).'</s>' : CHtml::encode($employees[$empId]['name']);
                                                if ($isAdmin) {
                                                    $triggerClass = 'emp-popup-trigger';
                                                    $dbBank = isset($employees[$empId]['schedule_bank']) ? $employees[$empId]['schedule_bank'] : 'none';
                                                    $dbState = isset($employees[$empId]['schedule_state']) ? $employees[$empId]['schedule_state'] : 'normal';
                                                    $dbStateEnd = isset($employees[$empId]['schedule_state_end']) ? $employees[$empId]['schedule_state_end'] : '';
                                                    $dbQuitting = isset($employees[$empId]['schedule_is_quitting']) && $employees[$empId]['schedule_is_quitting'] == 1 ? 'yes' : 'no';
                                                    $dbQuitDate = isset($employees[$empId]['schedule_quitting_date']) ? $employees[$empId]['schedule_quitting_date'] : '';
                                                    $dbDays = isset($employees[$empId]['schedule_preferred_days']) ? $employees[$empId]['schedule_preferred_days'] : '';
                                                    $dbComment = isset($employees[$empId]['schedule_comment']) ? $employees[$empId]['schedule_comment'] : '';
                                                    $dbPhone = isset($employees[$empId]['phone']) ? $employees[$empId]['phone'] : '';
                                                    $mockAutoStatus = $employeeAutoStatus($empId, $employees[$empId]);
                                                    $avatarUrl = !empty($employees[$empId]['avatar']) ? Yii::app()->request->baseUrl.'/uploads/employee/avatar/'.CHtml::encode($employees[$empId]['avatar']) : '';
                                                    $initial = mb_substr(!empty($employees[$empId]['first_name']) ? $employees[$empId]['first_name'] : $employees[$empId]['name'], 0, 1, 'UTF-8');
                                                    $triggerAttrs = ' data-emp-id="'.$empId.'"'
                                                        .' data-name="'.CHtml::encode($employees[$empId]['name']).'"'
                                                        .' data-phone="'.CHtml::encode($dbPhone).'"'
                                                        .' data-avatar-url="'.$avatarUrl.'"'
                                                        .' data-initial="'.CHtml::encode($initial).'"'
                                                        .' data-bank="'.CHtml::encode($dbBank).'"'
                                                        .' data-days="'.CHtml::encode($dbDays).'"'
                                                        .' data-comment="'.CHtml::encode($dbComment).'"'
                                                        .' data-auto-status="'.CHtml::encode($mockAutoStatus).'"'
                                                        .' data-state="'.CHtml::encode($dbState).'"'
                                                        .' data-state-end="'.CHtml::encode($dbStateEnd).'"'
                                                        .' data-quitting="'.$dbQuitting.'"'
                                                        .' data-quitting-date="'.CHtml::encode($dbQuitDate).'"';
                                                }
                                            }
                                            $statusClass = '';
                                            if ($empId && isset($shiftStatus[$fid][$d][$empId])) {
                                                $st = $shiftStatus[$fid][$d][$empId];
                                                if ($st == 'open') $statusClass = 'shift-open'; elseif ($st == 'closed') $statusClass = 'shift-closed'; elseif ($st == 'not_closed') $statusClass = 'shift-error';
                                            }
                                        ?>
                                            <?php $cellComment = isset($scheduleComments[$fid][$d]) ? $scheduleComments[$fid][$d] : ''; ?>
                                            <td class="filial-day edit-cell <?= $emptyClass ?> <?= $statusClass ?> <?= $isWeekend ? 'weekend-cell' : '' ?> <?= $todayClass ?>" data-type="filial" data-filial="<?= (int)$fid ?>" data-date="<?= CHtml::encode($d) ?>" data-value="<?= CHtml::encode($empId) ?>" data-comment="<?= CHtml::encode($cellComment) ?>" title="<?= CHtml::encode($plainName) ?>">
                                                <?php if ($nameHtml !== ''): ?>
<span class="filial-name <?= $triggerClass ?>" <?= $triggerAttrs ?>>
<?= $nameHtml ?>
</span>
<?php else: ?>
<span class="empty-mark">—</span>
<?php endif; ?>
                                                <?php if ($cellComment !== ''): ?>
<span class="schedule-note-indicator" tabindex="0" aria-label="Комментарий к ячейке" data-note="<?= CHtml::encode($cellComment) ?>">!</span>
<?php endif; ?>
                                                <?php if ($isPrivileged): ?>
<button type="button" class="edit-comment-btn" title="Добавить или изменить комментарий">✎</button>
<?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="rest-main">
                <div class="card">
                    <div class="card-header-x">
<h3>Статусы сотрудников</h3>
</div>
                    <div class="rest-body">
                        <input type="date" id="restDatePicker" class="rest-date" value="<?= CHtml::encode($restDate) ?>">
                        <input type="text" id="statusSearchInput" class="rest-date" placeholder="Поиск по имени..." style="margin-bottom:0;">
                    </div>
                    <div class="status-container" id="restEmployeesBody">
                        <?php foreach ($statusGroups as $groupId => $group): ?>
                            <?php if (empty($group['employees']) && empty($group['shift_employees'])) continue; ?>
                            <div class="status-group">
                                <div class="status-group-title">
<?= CHtml::encode($group['title']) ?>
</div>
                                <?php foreach ($group['employees'] as $empId => $emp):
                                    $autoStatus = $emp['_auto_status'];
                                    $state = $emp['_state'];
                                    $phone = isset($emp['phone']) ? trim((string)$emp['phone']) : '';
                                    if ($state === 'sick') { $badgeText = 'Болеет'; $badgeClass = 'sick'; }
                                    elseif ($state === 'vacation') { $badgeText = 'Отпуск'; $badgeClass = 'vacation'; }
                                    else { $badgeText = $autoStatusLabels[$autoStatus]; $badgeClass = $autoStatus; }
                                    $triggerClass = $isAdmin ? 'emp-popup-trigger' : '';
                                    $triggerAttrs = '';
                                    $avatarUrl = !empty($emp['avatar']) ? Yii::app()->request->baseUrl.'/uploads/employee/avatar/'.CHtml::encode($emp['avatar']) : '';
                                    $initial = mb_substr(!empty($emp['first_name']) ? $emp['first_name'] : $emp['name'], 0, 1, 'UTF-8');
                                    if ($isAdmin) {
                                        $dbBank = isset($emp['schedule_bank']) ? $emp['schedule_bank'] : 'none';
                                        $dbState = isset($emp['schedule_state']) ? $emp['schedule_state'] : 'normal';
                                        $dbStateEnd = isset($emp['schedule_state_end']) ? $emp['schedule_state_end'] : '';
                                        $dbQuitting = isset($emp['schedule_is_quitting']) && $emp['schedule_is_quitting'] == 1 ? 'yes' : 'no';
                                        $dbQuitDate = isset($emp['schedule_quitting_date']) ? $emp['schedule_quitting_date'] : '';
                                        $dbDays = isset($emp['schedule_preferred_days']) ? $emp['schedule_preferred_days'] : '';
                                        $dbComment = isset($emp['schedule_comment']) ? $emp['schedule_comment'] : '';
                                        $triggerAttrs = ' data-emp-id="'.$empId.'"'
                                            .' data-name="'.CHtml::encode($emp['name']).'"'
                                            .' data-phone="'.CHtml::encode($phone).'"'
                                            .' data-avatar-url="'.$avatarUrl.'"'
                                            .' data-initial="'.CHtml::encode($initial).'"'
                                            .' data-bank="'.CHtml::encode($dbBank).'"'
                                            .' data-days="'.CHtml::encode($dbDays).'"'
                                            .' data-comment="'.CHtml::encode($dbComment).'"'
                                            .' data-auto-status="'.CHtml::encode($autoStatus).'"'
                                            .' data-state="'.CHtml::encode($dbState).'"'
                                            .' data-state-end="'.CHtml::encode($dbStateEnd).'"'
                                            .' data-quitting="'.$dbQuitting.'"'
                                            .' data-quitting-date="'.CHtml::encode($dbQuitDate).'"';
                                    }
                                ?>
                                    <div class="status-item">
                                        <div class="status-item-info">
                                            <?php if ($avatarUrl): ?>
<img data-avatar-src="<?= CHtml::encode($avatarUrl) ?>" data-avatar-initial="<?= CHtml::encode($initial) ?>" class="status-avatar" alt="" loading="lazy" decoding="async">
<?php else: ?>
<span class="status-avatar">
<?= $initial ?>
</span>
<?php endif; ?>
                                            <span class="status-name">
                                                <a href="#" class="call-mini call-btn" data-phone="<?= CHtml::encode($phone) ?>" title="<?= $phone !== '' ? 'Позвонить: '.CHtml::encode($phone) : 'Номер не указан' ?>">
<?= $phoneSvg ?>
</a>
                                                <span class="<?= $triggerClass ?>" <?= $triggerAttrs ?>>
<?= CHtml::encode($emp['name']) ?>
</span>
                                            </span>
                                        </div>
                                        <div class="status-actions">
<span class="status-badge <?= $badgeClass ?>">
<?= $badgeText ?>
</span>
</div>
                                    </div>
                                <?php endforeach; ?>

                                <?php if (!empty($group['shift_employees'])): ?>
                                    <?php $shiftCount = count($group['shift_employees']); ?>
                                    <div class="shift-toggle" data-target="shift-list-<?= $groupId ?>">
<span>Сотрудников на смене — <?= $shiftCount ?>
</span>
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
<polyline points="6 9 12 15 18 9">
</polyline>
</svg>
</div>
                                    <div class="shift-employees-container" id="shift-list-<?= $groupId ?>" style="display:none;">
                                        <?php foreach ($group['shift_employees'] as $empId => $emp):
                                            $status = $emp['_auto_status'];
                                            $statusLabel = $autoStatusLabels[$status];
                                            $phone = isset($emp['phone']) ? trim((string)$emp['phone']) : '';
                                            $triggerClass = $isAdmin ? 'emp-popup-trigger' : '';
                                            $triggerAttrs = '';
                                            $avatarUrl = !empty($emp['avatar']) ? Yii::app()->request->baseUrl.'/uploads/employee/avatar/'.CHtml::encode($emp['avatar']) : '';
                                            $initial = mb_substr(!empty($emp['first_name']) ? $emp['first_name'] : $emp['name'], 0, 1, 'UTF-8');
                                            if ($isAdmin) {
                                                $dbBank = isset($emp['schedule_bank']) ? $emp['schedule_bank'] : 'none';
                                                $dbState = isset($emp['schedule_state']) ? $emp['schedule_state'] : 'normal';
                                                $dbStateEnd = isset($emp['schedule_state_end']) ? $emp['schedule_state_end'] : '';
                                                $dbQuitting = isset($emp['schedule_is_quitting']) && $emp['schedule_is_quitting'] == 1 ? 'yes' : 'no';
                                                $dbQuitDate = isset($emp['schedule_quitting_date']) ? $emp['schedule_quitting_date'] : '';
                                                $dbDays = isset($emp['schedule_preferred_days']) ? $emp['schedule_preferred_days'] : '';
                                                $dbComment = isset($emp['schedule_comment']) ? $emp['schedule_comment'] : '';
                                                $triggerAttrs = ' data-emp-id="'.$empId.'"'
                                                    .' data-name="'.CHtml::encode($emp['name']).'"'
                                                    .' data-phone="'.CHtml::encode($phone).'"'
                                                    .' data-avatar-url="'.$avatarUrl.'"'
                                                    .' data-initial="'.CHtml::encode($initial).'"'
                                                    .' data-bank="'.CHtml::encode($dbBank).'"'
                                                    .' data-days="'.CHtml::encode($dbDays).'"'
                                                    .' data-comment="'.CHtml::encode($dbComment).'"'
                                                    .' data-auto-status="'.CHtml::encode($status).'"'
                                                    .' data-state="'.CHtml::encode($dbState).'"'
                                                    .' data-state-end="'.CHtml::encode($dbStateEnd).'"'
                                                    .' data-quitting="'.$dbQuitting.'"'
                                                    .' data-quitting-date="'.CHtml::encode($dbQuitDate).'"';
                                            }
                                        ?>
                                            <div class="status-item">
                                                <div class="status-item-info">
                                                    <?php if ($avatarUrl): ?>
<img data-avatar-src="<?= CHtml::encode($avatarUrl) ?>" data-avatar-initial="<?= CHtml::encode($initial) ?>" class="status-avatar" alt="" loading="lazy" decoding="async">
<?php else: ?>
<span class="status-avatar">
<?= $initial ?>
</span>
<?php endif; ?>
                                                    <span class="status-name">
<a href="#" class="call-mini call-btn" data-phone="<?= CHtml::encode($phone) ?>" title="<?= $phone !== '' ? 'Позвонить: '.CHtml::encode($phone) : 'Номер не указан' ?>">
<?= $phoneSvg ?>
</a>
<span class="<?= $triggerClass ?>" <?= $triggerAttrs ?>>
<?= CHtml::encode($emp['name']) ?>
</span>
</span>
                                                </div>
                                                <div class="status-actions">
<span class="status-badge <?= $status ?>">
<?= $statusLabel ?>
</span>
</div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($activeEmployeesForStatus)): ?>
<div style="padding:12px;color:#64748b;text-align:center;">Нет активных сотрудников</div>
<?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="section-title">
<span class="section-bar">
</span>Сотрудники по филиалам и отделам</div>
    <div class="card">
        <div class="card-header-x">
            <h3>Где работает сотрудник</h3>
            <?php if ($isMyMode): ?>
<span class="card-badge">только свои</span>
<?php else: ?>
<span class="card-badge">все сотрудники</span>
<?php endif; ?>
        </div>
        <div class="table-scroll">
            <table class="schedule-table emp-branch-table">
                <thead>
<tr>
                    <th class="col-branch">Сотрудник</th>
                    <?php foreach ($days as $d): ?>
                        <?php $w = date('w', strtotime($d)); $isWeekend = ($w == 0 || $w == 6); $isToday = ($d === $today); ?>
                        <th class="day-head <?= $isWeekend ? 'weekend-head' : '' ?> <?= $isToday ? 'today-head' : '' ?>" data-date="<?= CHtml::encode($d) ?>">
<div class="d-wd">
<?= $weekdays[$w] ?>
</div>
<div class="d-dt">
<?= date('d', strtotime($d)) ?>
</div>
</th>
                    <?php endforeach; ?>
                </tr>
</thead>
                <tbody>
                    <?php if (!empty($newTableEmployees)): ?>
                        <?php foreach ($newTableEmployees as $empId): ?>
                            <?php if (!isset($employees[$empId])) continue; $emp = $employees[$empId];
                                $triggerClass = '';
                                $triggerAttrs = '';
                                if ($isAdmin) {
                                    $triggerClass = 'emp-popup-trigger';
                                    $dbBank = isset($emp['schedule_bank']) ? $emp['schedule_bank'] : 'none';
                                    $dbState = isset($emp['schedule_state']) ? $emp['schedule_state'] : 'normal';
                                    $dbStateEnd = isset($emp['schedule_state_end']) ? $emp['schedule_state_end'] : '';
                                    $dbQuitting = isset($emp['schedule_is_quitting']) && $emp['schedule_is_quitting'] == 1 ? 'yes' : 'no';
                                    $dbQuitDate = isset($emp['schedule_quitting_date']) ? $emp['schedule_quitting_date'] : '';
                                    $dbDays = isset($emp['schedule_preferred_days']) ? $emp['schedule_preferred_days'] : '';
                                    $dbComment = isset($emp['schedule_comment']) ? $emp['schedule_comment'] : '';
                                    $dbPhone = isset($emp['phone']) ? $emp['phone'] : '';
                                    $mockAutoStatus = $employeeAutoStatus($empId, $emp);
                                    $avatarUrl = !empty($emp['avatar']) ? Yii::app()->request->baseUrl.'/uploads/employee/avatar/'.CHtml::encode($emp['avatar']) : '';
                                    $initial = mb_substr(!empty($emp['first_name']) ? $emp['first_name'] : $emp['name'], 0, 1, 'UTF-8');
                                    $triggerAttrs = ' data-emp-id="'.(int)$empId.'"'
                                        .' data-name="'.CHtml::encode($emp['name']).'"'
                                        .' data-phone="'.CHtml::encode($dbPhone).'"'
                                        .' data-avatar-url="'.$avatarUrl.'"'
                                        .' data-initial="'.CHtml::encode($initial).'"'
                                        .' data-bank="'.CHtml::encode($dbBank).'"'
                                        .' data-days="'.CHtml::encode($dbDays).'"'
                                        .' data-comment="'.CHtml::encode($dbComment).'"'
                                        .' data-auto-status="'.CHtml::encode($mockAutoStatus).'"'
                                        .' data-state="'.CHtml::encode($dbState).'"'
                                        .' data-state-end="'.CHtml::encode($dbStateEnd).'"'
                                        .' data-quitting="'.$dbQuitting.'"'
                                        .' data-quitting-date="'.CHtml::encode($dbQuitDate).'"';
                                }
                            ?>
                            <tr class="employee-work-row" data-employee-id="<?= (int)$empId ?>">
                                <td class="col-branch">
<div class="emp-cell">
                                    <?php if (!$isMyMode && !empty($emp['phone'])): ?>
<a href="#" class="call-mini call-btn" data-phone="<?= CHtml::encode($emp['phone']) ?>" title="Позвонить: <?= CHtml::encode($emp['phone']) ?>">
<?= $phoneSvg ?>
</a>
<?php endif; ?>
                                    <?php $rowAvatarUrl = !empty($emp['avatar']) ? Yii::app()->request->baseUrl.'/uploads/employee/avatar/'.CHtml::encode($emp['avatar']) : ''; ?>
                                    <?php $rowInitial = mb_substr(!empty($emp['first_name']) ? $emp['first_name'] : $emp['name'], 0, 1, 'UTF-8'); ?>
                                    <?php if ($rowAvatarUrl): ?>
<img class="employee-row-avatar" data-avatar-src="<?= CHtml::encode($rowAvatarUrl) ?>" data-avatar-initial="<?= CHtml::encode($rowInitial) ?>" alt="" loading="lazy" decoding="async">
<?php else: ?>
<span class="employee-row-avatar fallback">
<?= CHtml::encode($rowInitial) ?>
</span>
<?php endif; ?>
                                    <span class="emp-name <?= $triggerClass ?>" title="<?= CHtml::encode($emp['name']) ?>" <?= $triggerAttrs ?>>
<?= $emp['active'] == 0 ? '<s>'.CHtml::encode($emp['name']).'</s>' : CHtml::encode($emp['name']) ?>
</span>
                                </div>
</td>
                                <?php foreach ($days as $d):
                                    $w = date('w', strtotime($d));
                                    $isWeekend = ($w == 0 || $w == 6);
                                    $isToday = ($d === $today);
                                    $placesArr = isset($employeeWorkplaces[$empId][$d]) ? array_values($employeeWorkplaces[$empId][$d]) : array();
                                    $placeCount = count($placesArr);
                                    $firstPlace = isset($placesArr[0]) ? $placesArr[0]['name'] : null;
                                    $allNames = array();
                                    foreach ($placesArr as $p) $allNames[] = $p['name'];
                                    $statusClass = '';
                                    if (!empty($placesArr)) {
                                        foreach ($placesArr as $p) {
                                            if ($p['type'] === 'filial') {
                                                $bfid = $p['id'];
                                                $st = isset($shiftStatus[$bfid][$d][$empId]) ? $shiftStatus[$bfid][$d][$empId] : null;
                                                if ($st == 'not_closed') { $statusClass = 'shift-error'; break; }
                                                if ($st == 'open' && $statusClass !== 'shift-error') $statusClass = 'shift-open';
                                            if ($st == 'closed' && $statusClass === '') $statusClass = 'shift-closed';
                                            }
                                        }
                                    }
                                ?>
                                    <td class="filial-day employee-work-cell <?= $statusClass ?> <?= empty($placesArr) ? 'no-shift' : '' ?> <?= $isWeekend ? 'weekend-cell' : '' ?> <?= $isToday ? 'today-cell' : '' ?>" data-emp-id="<?= (int)$empId ?>" data-work-date="<?= CHtml::encode($d) ?>" title="<?= CHtml::encode(implode(', ', $allNames)) ?>">
                                        <?php if ($firstPlace): ?>
<span class="filial-name">
<?= CHtml::encode($firstPlace) ?>
<?= $placeCount > 1 ? ' +'.($placeCount - 1) : '' ?>
</span>
<?php else: ?>
<span class="no-shift-mark">—</span>
<?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
<td colspan="<?= count($days) + 1 ?>" style="text-align:left;color:#64748b;padding:12px 14px;">Нет данных о сменах сотрудников.</td>
</tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php
    $departmentsToShow = $departments;
    if ($isMyMode) {
        $departmentsToShow = array();
        foreach ($departments as $depKey => $depTitle) {
            $depRows = isset($departmentRows[$depKey]) ? $departmentRows[$depKey] : array();
            $myRows = array_filter($depRows, function ($row) use ($currentEmployeeId) {
                return !empty($row['employee_id']) && $row['employee_id'] == $currentEmployeeId;
            });
            if (!empty($myRows)) $departmentsToShow[$depKey] = $depTitle;
        }
    }
    ?>

    <?php if (!empty($departmentsToShow)): ?>
        <div class="section-title">
<span class="section-bar">
</span>Отделы</div>
        <?php foreach ($departmentsToShow as $depKey => $depTitle): ?>
            <?php
            $rows = isset($departmentRows[$depKey]) ? $departmentRows[$depKey] : array();
            if ($isMyMode) {
                $rows = array_filter($rows, function ($row) use ($currentEmployeeId) {
                    return !empty($row['employee_id']) && $row['employee_id'] == $currentEmployeeId;
                });
            }
            ?>
            <div class="card" data-department="<?= $depKey ?>">
                <div class="card-header-x">
                    <h3>
<?= CHtml::encode($depTitle) ?>
</h3>
                    <div class="dept-header-actions">
                        <?php if (!empty($rows)): ?>
<span class="card-badge">
<?= count($rows) ?> сотр.</span>
<?php endif; ?>
                        <?php if ($isAdmin): ?>
<button type="button" class="dept-add-row-btn" data-department="<?= CHtml::encode($depKey) ?>">+ Добавить сотрудника</button>
<?php endif; ?>
                    </div>
                </div>
                <div class="table-scroll">
                    <table class="schedule-table dept-table">
                        <thead>
<tr>
                            <th class="col-role">Должность</th>
                            <th class="col-emp">Сотрудник</th>
                            <?php foreach ($days as $d): ?>
                                <?php $w = date('w', strtotime($d)); $isWeekend = ($w == 0 || $w == 6); $isToday = ($d === $today); ?>
                                <th class="day-head <?= $isWeekend ? 'weekend-head' : '' ?> <?= $isToday ? 'today-head' : '' ?>" data-date="<?= CHtml::encode($d) ?>">
<div class="d-wd">
<?= $weekdays[$w] ?>
</div>
<div class="d-dt">
<?= date('d', strtotime($d)) ?>
</div>
</th>
                            <?php endforeach; ?>
                            <?php if ($isAdmin): ?>
<th class="dept-actions-col">Управление</th>
<?php endif; ?>
                        </tr>
</thead>
                        <tbody>
                            <?php if (!empty($rows)): ?>
                                <?php foreach ($rows as $row): ?>
                                    <tr data-id="<?= (int)$row['id'] ?>" data-role="<?= CHtml::encode($row['role']) ?>" data-employee-id="<?= (int)$row['employee_id'] ?>">
                                        <td class="col-role" title="<?= CHtml::encode($row['role']) ?>">
<?= CHtml::encode($row['role']) ?>
</td>
                                        <td class="col-emp">
                                            <?php
                                            $nameHtml = ''; $empName = ''; $triggerClass = ''; $triggerAttrs = '';
                                            if (!empty($row['employee_id']) && isset($employees[$row['employee_id']])) {
                                                $emp = $employees[$row['employee_id']];
                                                $empName = $emp['name'];
                                                $nameHtml = $emp['active'] == 0 ? '<s>'.CHtml::encode($emp['name']).'</s>' : CHtml::encode($emp['name']);
                                                if ($isAdmin) {
                                                    $triggerClass = 'emp-popup-trigger';
                                                    $dbBank = isset($emp['schedule_bank']) ? $emp['schedule_bank'] : 'none';
                                                    $dbState = isset($emp['schedule_state']) ? $emp['schedule_state'] : 'normal';
                                                    $dbStateEnd = isset($emp['schedule_state_end']) ? $emp['schedule_state_end'] : '';
                                                    $dbQuitting = isset($emp['schedule_is_quitting']) && $emp['schedule_is_quitting'] == 1 ? 'yes' : 'no';
                                                    $dbQuitDate = isset($emp['schedule_quitting_date']) ? $emp['schedule_quitting_date'] : '';
                                                    $dbDays = isset($emp['schedule_preferred_days']) ? $emp['schedule_preferred_days'] : '';
                                                    $dbComment = isset($emp['schedule_comment']) ? $emp['schedule_comment'] : '';
                                                    $dbPhone = isset($emp['phone']) ? $emp['phone'] : '';
                                                    $mockAutoStatus = $employeeAutoStatus($row['employee_id'], $emp);
                                                    $avatarUrl = !empty($emp['avatar']) ? Yii::app()->request->baseUrl.'/uploads/employee/avatar/'.CHtml::encode($emp['avatar']) : '';
                                                    $initial = mb_substr(!empty($emp['first_name']) ? $emp['first_name'] : $emp['name'], 0, 1, 'UTF-8');
                                                    $triggerAttrs = ' data-emp-id="'.$row['employee_id'].'"'
                                                        .' data-name="'.CHtml::encode($emp['name']).'"'
                                                        .' data-phone="'.CHtml::encode($dbPhone).'"'
                                                        .' data-avatar-url="'.$avatarUrl.'"'
                                                        .' data-initial="'.CHtml::encode($initial).'"'
                                                        .' data-bank="'.CHtml::encode($dbBank).'"'
                                                        .' data-days="'.CHtml::encode($dbDays).'"'
                                                        .' data-comment="'.CHtml::encode($dbComment).'"'
                                                        .' data-auto-status="'.CHtml::encode($mockAutoStatus).'"'
                                                        .' data-state="'.CHtml::encode($dbState).'"'
                                                        .' data-state-end="'.CHtml::encode($dbStateEnd).'"'
                                                        .' data-quitting="'.$dbQuitting.'"'
                                                        .' data-quitting-date="'.CHtml::encode($dbQuitDate).'"';
                                                }
                                            }
                                            ?>
                                            <?php $departmentEmployeePhone = (!empty($row['employee_id']) && isset($employees[$row['employee_id']]['phone'])) ? trim((string)$employees[$row['employee_id']]['phone']) : ''; ?>
                                            <div class="emp-cell">
                                                <?php if (!$isMyMode && $departmentEmployeePhone !== ''): ?>
<a href="#" class="call-mini call-btn" data-phone="<?= CHtml::encode($departmentEmployeePhone) ?>" title="Позвонить: <?= CHtml::encode($departmentEmployeePhone) ?>">
<?= $phoneSvg ?>
</a>
<?php endif; ?>
                                                <?php if ($empName !== '' && !empty($emp['avatar'])): ?>
<img class="employee-row-avatar" data-avatar-src="<?= CHtml::encode(Yii::app()->request->baseUrl.'/uploads/employee/avatar/'.$emp['avatar']) ?>" data-avatar-initial="<?= CHtml::encode(mb_substr(!empty($emp['first_name']) ? $emp['first_name'] : $empName, 0, 1, 'UTF-8')) ?>" alt="" loading="lazy" decoding="async">
                                                <?php elseif ($empName !== ''): ?>
<span class="employee-row-avatar fallback">
<?= CHtml::encode(mb_substr(!empty($emp['first_name']) ? $emp['first_name'] : $empName, 0, 1, 'UTF-8')) ?>
</span>
<?php endif; ?>
                                                <span class="emp-name <?= $triggerClass ?>" title="<?= CHtml::encode($empName) ?>" <?= $triggerAttrs ?>>
<?= $nameHtml !== '' ? $nameHtml : '—' ?>
</span>
                                            </div>
                                        </td>
                                        <?php foreach ($days as $d): ?>
                                            <?php $w = date('w', strtotime($d)); $isWeekend = ($w == 0 || $w == 6); $isToday = ($d === $today); $hasShift = !empty($schedule['department'][$row['id']][$d]); ?>
                                            <td class="day-cell edit-cell <?= $isWeekend ? 'weekend-cell' : '' ?> <?= $isToday ? 'today-cell' : '' ?>" data-type="department" data-dep="<?= $row['id'] ?>" data-emp-id="<?= $row['employee_id'] ?>" data-date="<?= CHtml::encode($d) ?>" data-value="<?= $hasShift ? '1' : '0' ?>">
                                                <span class="check-wrapper" style="<?= $hasShift ? '' : 'display:none;' ?>">
<span class="day-check" title="Работает">
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
<polyline points="20 6 9 17 4 12">
</polyline>
</svg>
</span>
</span>
                                                <span class="empty-wrapper" style="<?= $hasShift ? 'display:none;' : '' ?>">
<span class="day-empty">
</span>
</span>
                                            </td>
                                        <?php endforeach; ?>
                                        <?php if ($isAdmin): ?>
<td class="dept-row-actions">
<button type="button" class="dept-row-edit-btn" title="Изменить должность или сотрудника">Изменить</button>
<button type="button" class="dept-row-delete-btn" title="Удалить строку отдела">×</button>
</td>
<?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
<td colspan="<?= count($days) + 2 + ($isAdmin ? 1 : 0) ?>" style="text-align:left;color:#64748b;padding:12px 14px;">Нет данных</td>
</tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div id="emp-popup" class="emp-popup">
    <div class="emp-popup-header">
        <div class="emp-popup-header-left">
            <div class="emp-popup-avatar-wrap">
</div>
            <div>
<div class="emp-popup-name">
</div>
<div class="emp-popup-role">Сотрудник</div>
</div>
        </div>
        <div class="popup-phone-right">
            <div class="popup-phone-label" style="display:flex;justify-content:flex-end;margin-bottom:2px;">
                <button type="button" class="popup-call-btn call-btn" title="Позвонить" style="display:none;border:none;background:transparent;padding:0;width:20px;height:20px;">
<span style="color:#16a34a;width:16px;height:16px;display:inline-block;">
<?= $phoneSvg ?>
</span>
</button>
            </div>
            <div class="popup-phone-wrap">
<span class="popup-phone-number" title="Нажмите чтобы скопировать">—</span>
</div>
        </div>
    </div>
    <div class="emp-popup-body">
        <div class="popup-field">
<label>Берег</label>
<select class="popup-control popup-bank">
<option value="left">Левый</option>
<option value="right">Правый</option>
<option value="none">Не определено</option>
</select>
</div>
        <div class="popup-field">
            <label>Основные места работы и предпочитаемые дни</label>
            <div id="popup-filial-preferences" class="popup-filial-preferences">
</div>
            <button type="button" id="add-popup-filial-preference" class="popup-add-filial">+ Добавить филиал</button>
            <small class="popup-preference-hint">Один и тот же день недели можно закрепить только за одним филиалом.</small>
        </div>
        <div class="popup-field">
<label>Статус на сегодня</label>
<div class="popup-auto-status status-dayoff" id="popup-auto-status-display">Выходной</div>
</div>
        <div class="popup-field">
            <label>Состояние</label>
            <select class="popup-control popup-state state-normal">
<option value="normal">В норме</option>
<option value="sick">Болеет</option>
<option value="vacation">В отпуске</option>
</select>
            <div class="popup-date-wrapper">
<span class="popup-date-label">до</span>
<input type="date" class="popup-control popup-date">
<label class="popup-date-checkbox">
<input type="checkbox" class="popup-date-undefined"> Не определено</label>
</div>
        </div>
        <div class="popup-field">
<label>Планирует уволиться</label>
<div class="popup-quitting-row">
<select class="popup-control popup-quitting quitting-no">
<option value="no">Нет</option>
<option value="yes">Да</option>
</select>
<div class="popup-quitting-date-wrapper">
<span class="popup-date-label">когда</span>
<input type="date" class="popup-control popup-quitting-date">
</div>
</div>
</div>
        <div class="popup-field">
<label>Комментарий</label>
<textarea class="popup-control popup-comment" rows="2">
</textarea>
</div>
    </div>
</div>

<div id="avatar-modal">
<img alt="Фото сотрудника">
</div>
<div id="schedule-note-tooltip" class="schedule-note-tooltip" role="tooltip">
</div>
<div id="schedule-comment-modal" class="schedule-modal" aria-hidden="true">
    <div class="schedule-modal-card">
        <h3 class="schedule-modal-title">Комментарий к филиалу и дате</h3>
        <textarea id="schedule-comment-input" maxlength="1000" placeholder="Например: филиал закрыт на ремонт, вход со двора...">
</textarea>
        <div class="schedule-modal-actions">
<button type="button" class="mode-btn" id="cancelScheduleComment">Отмена</button>
<button type="button" class="btn-primary-x" id="saveScheduleComment">Сохранить</button>
</div>
    </div>
</div>
<div id="backup-history-modal" class="schedule-modal" aria-hidden="true">
    <div class="schedule-modal-card">
        <h3 class="schedule-modal-title">Сохранённые версии графика</h3>
        <div id="schedule-backup-list" class="backup-list">
<div class="backup-empty">Загрузка истории...</div>
</div>
        <?php if ($isAdmin): ?>
<div class="schedule-modal-actions" style="justify-content:flex-start;">
<button type="button" class="mode-btn delete-all-backups-btn" id="deleteAllScheduleBackupsBtn">Удалить ВСЕ резервные копии</button>
</div>
<?php endif; ?>
        <div class="schedule-modal-actions">
<button type="button" class="mode-btn" id="closeBackupHistory">Закрыть</button>
</div>
    </div>
</div>
<?php if ($isAdmin): ?>
<div id="department-row-modal" class="schedule-modal" aria-hidden="true">
    <div class="schedule-modal-card">
        <h3 class="schedule-modal-title" id="department-row-modal-title">Добавить сотрудника в отдел</h3>
        <div class="dept-modal-fields">
            <div>
<label for="department-row-role">Должность</label>
<input type="text" id="department-row-role" maxlength="255" placeholder="Например, мастер-приёмщик">
</div>
            <div>
<label for="department-row-employee">Сотрудник</label>
<select id="department-row-employee">
<option value="">— Не назначен —</option>
</select>
</div>
        </div>
        <div class="schedule-modal-actions department-modal-actions">
<button type="button" class="mode-btn" id="cancelDepartmentRow">Отмена</button>
<button type="button" class="btn-primary-x" id="saveDepartmentRow" style="display:inline-flex!important;visibility:visible!important;opacity:1!important;position:static!important;min-width:132px!important;min-height:42px!important;flex:0 0 auto!important;background:#635bdb!important;background-image:linear-gradient(135deg,#746be8,#554bc8)!important;color:#fff!important;-webkit-text-fill-color:#fff!important;border:0!important;border-radius:10px!important;font-weight:800!important;box-shadow:0 6px 16px rgba(91,81,201,.28)!important;">Сохранить</button>
</div>
    </div>
</div>
<?php endif; ?>

<script>

$.ajaxSetup({beforeSend:function(xhr){xhr.setRequestHeader('X-CSRF-Token','<?= Yii::app()->request->csrfToken ?>');}});

var scheduleToastTimer = null;
var lastScheduleToastText = '';
var lastScheduleToastAt = 0;
window.showToast = function(message, isError) {
    var text = String(message || '');
    var now = Date.now();
    if (!isError && text === 'Сохранено' && text === lastScheduleToastText && (now - lastScheduleToastAt) < 1100) return;
    lastScheduleToastText = text;
    lastScheduleToastAt = now;
    clearTimeout(scheduleToastTimer);
    var $toast = $('#schedule-toast');
    if (!$toast.length) $toast = $('<div id="schedule-toast" role="status" aria-live="polite"></div>').appendTo('body');
    $toast.stop(true,true).removeClass('is-visible is-error is-success').addClass(isError ? 'is-error' : 'is-success').text(text);
    requestAnimationFrame(function(){$toast.addClass('is-visible');});
    scheduleToastTimer = setTimeout(function(){$toast.removeClass('is-visible');},2600);
};

$('#scheduleFilters select').on('change',function(){
    var $form=$('#scheduleFilters');
    var type=$form.find('[name="view_type"]').val();
    if((type==='day'||type==='week')&&(this.name==='month'||this.name==='year')){
        var y=$form.find('[name="year"]').val();
        var m=String($form.find('[name="month"]').val());
        if(m.length<2)m='0'+m;
        $form.find('[name="start_date"]').val(y+'-'+m+'-01');
    }
    $form.trigger('submit');
});

var popupTimeout;
var isInteractingWithDate = false;
var currentEmpId = null;
var currentTrigger = null;
var isUpdatingPopup = false;
var isEditMode = <?= $isPrivileged ? 'true' : 'false' ?>;
var employeesList = <?= CJSON::encode($employees) ?>;
var filialsList = <?= CJSON::encode($employeePreferenceTargets) ?>;
var assignMap = <?= CJSON::encode($assignMap) ?>;
var schedulePlaceNames = <?= CJSON::encode($schedulePlaceNames) ?>;

function refreshEmployeeWorkplaceCell(date, employeeId) {
    var selector='.employee-work-cell[data-emp-id="'+String(employeeId)+'"][data-work-date="'+String(date)+'"]';
    var $cell=$(selector);
    if(!$cell.length)return;
    var dayMap=assignMap[date]||{};
    var placeKeys=normalizePlaceList(dayMap[String(employeeId)]||dayMap[employeeId]);
    var names=[];
    $.each(placeKeys,function(i,key){var label=schedulePlaceNames[key];if(label&&$.inArray(label,names)===-1)names.push(label);});
    $cell.empty().toggleClass('no-shift',names.length===0).attr('title',names.join(', '));
    if(names.length)$('<span class="filial-name">').text(names[0]+(names.length>1?' +'+(names.length-1):'')).appendTo($cell);
    else $('<span class="no-shift-mark">').text('—').appendTo($cell);
}

var editingCommentCell = null;
var avatarBaseUrl = <?= CJSON::encode(Yii::app()->request->baseUrl . '/uploads/employee/avatar/') ?>;
var scheduleCsvRows = <?= CJSON::encode($csvExportRows) ?>;

function scheduleCsvEscape(value) {
    var text = value === null || typeof value === 'undefined' ? '' : String(value);
    return '"' + text.replace(/"/g, '""') + '"';
}
var employeeAvatarCache = (function () {
    var CACHE_NAME = 'employee-avatars-v1';
    var CACHE_TTL = <?= $isAdmin ? (6 * 60 * 60 * 1000) : (48 * 60 * 60 * 1000) ?>;
    var MAX_CACHE_ITEMS = 300;
    var inflight = {};
    var observer = null;
    var mutationObserver = null;

    function canUseCacheApi() {
        return !!(
            window.caches &&
            window.fetch &&
            window.URL &&
            window.URL.createObjectURL &&
            window.Response &&
            window.Headers
        );
    }

    function trimCache(cache) {
        return cache.keys().then(function (keys) {
            var excess = keys.length - MAX_CACHE_ITEMS;
            if (excess <= 0) return Promise.resolve();

            var chain = Promise.resolve();
            for (var i = 0; i < excess; i++) {
                (function (request) {
                    chain = chain.then(function () {
                        return cache.delete(request);
                    });
                })(keys[i]);
            }
            return chain;
        }).catch(function () {
            // Failure to trim the cache must not prevent an avatar from displaying.
        });
    }

    function fetchAvatarBlob(url) {
        return window.caches.open(CACHE_NAME).then(function (cache) {
            return cache.match(url).then(function (cachedResponse) {
                if (!cachedResponse) return null;

                var cachedAt = parseInt(cachedResponse.headers.get('X-Avatar-Cached-At') || '0', 10);
                if (cachedAt > 0 && (Date.now() - cachedAt) < CACHE_TTL) {
                    return cachedResponse.blob();
                }

                return cache.delete(url).then(function () {
                    return null;
                });
            }).then(function (cachedBlob) {
                if (cachedBlob) return cachedBlob;

                return window.fetch(url, {
                    credentials: 'same-origin',
                    cache: 'no-store'
                }).then(function (response) {
                    if (!response || !response.ok) {
                        throw new Error('Не удалось загрузить аватарку');
                    }
                    return response.blob().then(function (blob) {
                        var headers = new window.Headers();
                        headers.set('Content-Type', blob.type || 'application/octet-stream');
                        headers.set('X-Avatar-Cached-At', String(Date.now()));

                        return cache.put(url, new window.Response(blob, { headers: headers }))
                            .then(function () { return trimCache(cache); })
                            .catch(function () {
                                // Show the fetched blob even when Cache Storage is full/unavailable.
                            })
                            .then(function () { return blob; });
                    });
                });
            });
        });
    }

    function addClass(element, className) {
        if (!element) return;
        if ((' ' + element.className + ' ').indexOf(' ' + className + ' ') === -1) {
            element.className = (element.className ? element.className + ' ' : '') + className;
        }
    }

    function removeClass(element, className) {
        if (!element) return;
        element.className = (' ' + element.className + ' ').replace(' ' + className + ' ', ' ').replace(/^\s+|\s+$/g, '');
    }

    function preparePlaceholder(img) {
        if (img.getAttribute('data-avatar-frame') === '1') {
            var existingFrame = img.parentNode;
            var existingPlaceholder = existingFrame && existingFrame.querySelector
                ? existingFrame.querySelector('.avatar-image-placeholder')
                : null;
            if (existingPlaceholder) {
                existingPlaceholder.textContent = img.getAttribute('data-avatar-initial') || '?';
            }
            return existingFrame;
        }

        var frame = document.createElement('span');
        frame.className = 'avatar-image-frame';
        var imageClasses = ' ' + (img.className || '') + ' ';
        if (imageClasses.indexOf(' status-avatar ') !== -1) addClass(frame, 'status-avatar-frame');
        else if (imageClasses.indexOf(' employee-row-avatar ') !== -1) addClass(frame, 'employee-row-avatar-frame');
        else if (imageClasses.indexOf(' employee-option-avatar ') !== -1) addClass(frame, 'employee-option-avatar-frame');
        else if ($(img).closest('.emp-popup-avatar-wrap').length) addClass(frame, 'popup-avatar-frame');
        else if ($(img).closest('#avatar-modal').length) addClass(frame, 'avatar-modal-frame');

        var placeholder = document.createElement('span');
        placeholder.className = 'avatar-image-placeholder';
        placeholder.setAttribute('aria-hidden', 'true');
        placeholder.textContent = img.getAttribute('data-avatar-initial') || '?';
        frame.appendChild(placeholder);

        if (img.parentNode) {
            img.parentNode.insertBefore(frame, img);
            frame.appendChild(img);
        }
        addClass(img, 'avatar-image-source');
        img.setAttribute('data-avatar-frame', '1');
        return frame;
    }

    function setImageSource(img, source, isObjectUrl) {
        var frame = preparePlaceholder(img);
        removeClass(frame, 'is-loaded');
        removeClass(frame, 'has-error');
        img.onload = function () {
            addClass(frame, 'is-loaded');
            removeClass(frame, 'has-error');
            img.setAttribute('data-avatar-state', 'loaded');
            if (isObjectUrl) window.URL.revokeObjectURL(source);
            img.onload = null;
            img.onerror = null;
        };
        img.onerror = function () {
            removeClass(frame, 'is-loaded');
            addClass(frame, 'has-error');
            img.setAttribute('data-avatar-state', 'error');
            if (isObjectUrl) window.URL.revokeObjectURL(source);
            img.onload = null;
            img.onerror = null;
        };
        img.src = source;
    }

    function setImageBlob(img, blob) {
        var objectUrl = window.URL.createObjectURL(blob);
        setImageSource(img, objectUrl, true);
    }

    function load(img) {
        if (!img || !img.getAttribute) return;

        var url = img.getAttribute('data-avatar-src');
        if (!url) return;

        var state = img.getAttribute('data-avatar-state');
        if (state === 'loading' || state === 'loaded' || state === 'native') return;
        img.setAttribute('data-avatar-state', 'loading');

        if (!canUseCacheApi()) {
            setImageSource(img, url, false);
            img.setAttribute('data-avatar-state', 'native');
            return;
        }

        var promise = inflight[url];
        if (!promise) {
            promise = fetchAvatarBlob(url);
            inflight[url] = promise;
        }

        promise.then(function (blob) {
            if (inflight[url] === promise) delete inflight[url];
            if (!document.documentElement.contains(img)) return;
            if (blob) {
                setImageBlob(img, blob);
            } else {
                setImageSource(img, url, false);
                img.setAttribute('data-avatar-state', 'native');
            }
        }).catch(function () {
            if (inflight[url] === promise) delete inflight[url];
            if (!document.documentElement.contains(img)) return;
            // On network/cache failure, fall back to the browser's normal image loader.
            setImageSource(img, url, false);
            img.setAttribute('data-avatar-state', 'native');
        });
    }

    function register(img, immediate) {
        if (!img || !img.getAttribute || !img.getAttribute('data-avatar-src')) return;

        if (img.getAttribute('data-avatar-ready') === '1') {
            if (immediate && img.getAttribute('data-avatar-state') !== 'loaded' &&
                img.getAttribute('data-avatar-state') !== 'loading' &&
                img.getAttribute('data-avatar-state') !== 'native') {
                if (observer) observer.unobserve(img);
                load(img);
            }
            return;
        }

        img.setAttribute('data-avatar-ready', '1');
        img.setAttribute('loading', 'lazy');
        img.setAttribute('decoding', 'async');

        if (immediate) {
            load(img);
        } else if (observer) {
            observer.observe(img);
        } else {
            // Older browser: native loading with the initial-letter placeholder.
            setImageSource(img, img.getAttribute('data-avatar-src'), false);
            img.setAttribute('data-avatar-state', 'native');
        }
    }

    function scan(root) {
        if (!root) return;
        if (root.nodeType === 1 && root.matches && root.matches('img[data-avatar-src]')) {
            register(root, false);
        }
        if (root.querySelectorAll) {
            var images = root.querySelectorAll('img[data-avatar-src]');
            for (var i = 0; i < images.length; i++) register(images[i], false);
        }
    }

    function init() {
        if ('IntersectionObserver' in window) {
            observer = new window.IntersectionObserver(function (entries) {
                for (var i = 0; i < entries.length; i++) {
                    if (entries[i].isIntersecting || entries[i].intersectionRatio > 0) {
                        var img = entries[i].target;
                        observer.unobserve(img);
                        load(img);
                    }
                }
            }, {
                root: null,
                rootMargin: '180px 0px',
                threshold: 0.01
            });
        }

        scan(document);

        if ('MutationObserver' in window && document.body) {
            mutationObserver = new window.MutationObserver(function (mutations) {
                for (var i = 0; i < mutations.length; i++) {
                    var nodes = mutations[i].addedNodes;
                    for (var j = 0; j < nodes.length; j++) {
                        if (nodes[j].nodeType === 1) scan(nodes[j]);
                    }
                }
            });
            mutationObserver.observe(document.body, { childList: true, subtree: true });
        }
    }

    return {
        init: init,
        register: register,
        load: load
    };
})();

$(function(){
    var popupEl=document.getElementById('emp-popup');
    if(!popupEl)return;
    var scheduleRoot=document.querySelector('.schedule-page');
    if(scheduleRoot&&window.getComputedStyle){
        var rootStyle=window.getComputedStyle(scheduleRoot);
        ['--text','--line','--muted','--primary','--line-2'].forEach(function(name){var value=rootStyle.getPropertyValue(name);if(value)popupEl.style.setProperty(name,value.trim());});
    }
    document.body.appendChild(popupEl);
});

function formatPhone(phone){
    if(!phone)return '';
    var digits=phone.replace(/\D/g,'');
    if(digits.length===11&&(digits[0]==='8'||digits[0]==='7'))return '+7 ('+digits.substring(1,4)+') '+digits.substring(4,7)+'-'+digits.substring(7,9)+'-'+digits.substring(9,11);
    return phone;
}
function copyToClipboard(text){
    if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(text).then(function(){if(typeof showToast==='function')showToast('Номер скопирован',false);});
}
    else{
        var textArea=document.createElement('textarea');
textArea.value=text;
document.body.appendChild(textArea);
textArea.focus();
textArea.select();
        try{document.execCommand('copy');
if(typeof showToast==='function')showToast('Номер скопирован',false);
}
        catch(err){if(typeof showToast==='function')showToast('Ошибка копирования',true);
}
        document.body.removeChild(textArea);
    }
}
function updateStateUI(){
    var val=$('.popup-state').val();
    var $stateSelect=$('.popup-state');
    $stateSelect.removeClass('state-normal state-sick state-vacation').addClass('state-'+val);
    if(val==='sick'||val==='vacation')$('.popup-date-wrapper').css('display','flex');
    else{$('.popup-date-wrapper').css('display','none');
$('.popup-date').val('');
syncScheduleDateField($('.popup-date'));
$('.popup-date-undefined').prop('checked',false).trigger('change');
}
}
function updateQuittingUI(){
    var val=$('.popup-quitting').val();
    var $quittingSelect=$('.popup-quitting');
    $quittingSelect.removeClass('quitting-yes quitting-no');
    if(val==='yes'){$quittingSelect.addClass('quitting-yes');
$('.popup-quitting-date-wrapper').css('display','flex');
}
    else{$quittingSelect.addClass('quitting-no');
$('.popup-quitting-date-wrapper').css('display','none');
$('.popup-quitting-date').val('');
syncScheduleDateField($('.popup-quitting-date'));
}
}
$(document).on('change','.popup-state',function(){updateStateUI();});
$(document).on('change','.popup-quitting',function(){updateQuittingUI();});
$(document).on('change','.popup-date-undefined',function(){
    var isChecked=$(this).is(':checked');
    var $dateInput=$('.popup-date');
    if(isChecked)$dateInput.val('').prop('disabled',true);else $dateInput.prop('disabled',false);
    syncScheduleDateField($dateInput);
});
$(document).on('click','.popup-phone-number',function(){var rawPhone=$(this).attr('data-raw');if(rawPhone)copyToClipboard(rawPhone);});
$(document).on('click', '.emp-popup-avatar-wrap', function (e) {
    e.stopPropagation();
    var $source = $(this).find('img').first();
    var src = $source.attr('data-avatar-src') || '';
    if (src) {
        rememberScheduleModalFocus();
        var $modalImage = $('#avatar-modal img');
        $modalImage.removeAttr('src data-avatar-ready data-avatar-state')
            .attr('data-avatar-src', src)
            .attr('data-avatar-initial', $source.attr('data-avatar-initial') || '?');
        $('#avatar-modal').attr('tabindex', '-1').trigger('focus').css('display', 'flex');
        employeeAvatarCache.register($modalImage[0], true);
    }
});
$(document).on('click','#avatar-modal',function(){closeAvatarModal();});
$(document).on('focus','.popup-date, .popup-quitting-date',function(){isInteractingWithDate=true;clearTimeout(popupTimeout);});
$(document).on('blur','.popup-date, .popup-quitting-date',function(){setTimeout(function(){isInteractingWithDate=false;},200);});

function positionEmpPopup($trigger,prepare){
    var $popup=$('#emp-popup');
    if(!$popup.length||!$trigger||!$trigger.length||!document.documentElement.contains($trigger[0]))return;
    if(prepare)$popup.css({display:'block',visibility:'hidden',left:'0px',top:'0px'});
    else if(!$popup.hasClass('active'))return;
    var gutter=12;
    var viewportW=Math.max(document.documentElement.clientWidth||0,window.innerWidth||0);
    var viewportH=Math.max(document.documentElement.clientHeight||0,window.innerHeight||0);
    var rect=$trigger[0].getBoundingClientRect();
    var popupW=Math.min(330,Math.max(240,viewportW-gutter*2));
    $popup.css({position:'fixed',margin:0,width:popupW+'px',maxWidth:(viewportW-gutter*2)+'px',height:'auto',maxHeight:(viewportH-gutter*2)+'px',overflowY:'auto',overflowX:'hidden',boxSizing:'border-box'});
    var popupH=Math.min($popup[0].scrollHeight||$popup.outerHeight(),viewportH-gutter*2);
    var spaceAbove=Math.max(0,rect.top-gutter-8);
    var spaceBelow=Math.max(0,viewportH-rect.bottom-gutter-8);
    var placeBelow=(spaceBelow>=popupH)||(spaceBelow>=spaceAbove);
    var availableHeight=placeBelow?spaceBelow:spaceAbove;
    var maxPopupH=Math.max(150,Math.min(viewportH-gutter*2,availableHeight));
    $popup.css('maxHeight',maxPopupH+'px');
    popupH=Math.min($popup.outerHeight(),maxPopupH);
    var top=placeBelow?rect.bottom+8:rect.top-popupH-8;
    top=Math.max(gutter,Math.min(top,viewportH-popupH-gutter));
    var spaceLeft=rect.left-gutter;
    var spaceRight=viewportW-rect.right-gutter;
    var placeLeft=(spaceLeft>=popupW+8)||(spaceLeft>spaceRight);
    var left=placeLeft?rect.left-popupW-8:rect.right+8;
    left=Math.max(gutter,Math.min(left,viewportW-popupW-gutter));
    var arrowTop=rect.top+(rect.height/2)-top-6;
    arrowTop=Math.max(18,Math.min(arrowTop,Math.max(18,popupH-28)));
    $popup.removeClass('left-arrow right-arrow').addClass(placeLeft?'left-arrow':'right-arrow').css({left:Math.round(left)+'px',top:Math.round(top)+'px',display:'block',visibility:'visible'});
    if($popup[0].style.setProperty)$popup[0].style.setProperty('--emp-popup-arrow-top',Math.round(arrowTop)+'px');
}

var preferenceDayNames={1:'Пн',2:'Вт',3:'Ср',4:'Чт',5:'Пт',6:'Сб',7:'Вс'};
function preferenceRowHtml(pref){
    pref=pref||{};
    var $row=$('<div class="popup-filial-preference-row"></div>');
    var $head=$('<div class="popup-filial-preference-head"></div>').appendTo($row);
    var $select=$('<select class="popup-control pref-filial-select"><option value="">Выберите место работы</option></select>').appendTo($head);
    $.each(filialsList,function(id,name){$('<option></option>').val(id).text(name).appendTo($select);});
    $select.val(pref.filial_id?String(pref.filial_id):'');
    $select.data('previous',$select.val());
    $('<button type="button" class="popup-remove-filial" title="Удалить филиал">×</button>').appendTo($head);
    var $days=$('<div class="popup-preference-days"></div>').appendTo($row);
    $.each(preferenceDayNames,function(day,label){
        $('<button type="button" class="pref-day-pill"></button>').attr('data-day',day).text(label)
            .toggleClass('active',(pref.days||[]).map(String).indexOf(String(day))!==-1).appendTo($days);
    });
    return $row;
}
function renderEmployeeFilialPreferences(emp){
    var $container=$('#popup-filial-preferences').empty();
    var prefs=emp&&emp.schedule_filial_preferences?emp.schedule_filial_preferences:{};
    if(typeof prefs==='string'){try{prefs=JSON.parse(prefs);
}catch(e){prefs={};
}}
    var rows=[];
    $.each(prefs,function(fid,days){rows.push({filial_id:fid,days:days||[]});});
    if(!rows.length&&emp&&emp.schedule_primary_filial_id)rows.push({filial_id:emp.schedule_primary_filial_id,days:[]});
    if(!rows.length)rows.push({filial_id:'',days:[]});
    rows.forEach(function(pref){$container.append(preferenceRowHtml(pref));});
}
function collectFilialPreferences(){
    var rows=[];
var usedDays={};
var error='';
    $('#popup-filial-preferences .popup-filial-preference-row').each(function(){
        var $row=$(this);var filialId=String($row.find('.pref-filial-select').val()||'');var days=[];
        $row.find('.pref-day-pill.active').each(function(){days.push(parseInt($(this).attr('data-day'),10));});
        if(!filialId&&days.length){error='Сначала выберите филиал для отмеченных дней.';return false;}
        if(filialId&&days.length){
            days.forEach(function(day){if(usedDays[day]&&usedDays[day]!==filialId)error='День '+preferenceDayNames[day]+' уже закреплён за другим филиалом.';usedDays[day]=filialId;});
            rows.push({filial_id:filialId,days:days});
        }
    });
    return {rows:rows,error:error};
}
function saveEmployeeFilialPreferences(){
    if(!currentEmpId||!currentTrigger||isUpdatingPopup)return;
    var collected=collectFilialPreferences();
    if(collected.error){if(typeof showToast==='function')showToast(collected.error,true);
return;
}
    $.ajax({
        url:'<?= $this->createUrl("saveEmployeeFilialPreferences") ?>',type:'POST',dataType:'json',
        data:{emp_id:currentEmpId,preferences:JSON.stringify(collected.rows),'<?= Yii::app()->request->csrfTokenName ?>':'<?= Yii::app()->request->csrfToken ?>'},
        success:function(res){
            if(res.status==='success'){
                var grouped={};var allDays=[];
                $.each(collected.rows,function(_,row){grouped[row.filial_id]=row.days;$.each(row.days,function(__,d){allDays.push(d);});});
                if(employeesList[currentEmpId]){
                    employeesList[currentEmpId].schedule_filial_preferences=grouped;
                    var first=collected.rows.length?parseInt(collected.rows[0].filial_id,10):null;
                    employeesList[currentEmpId].schedule_primary_filial_id=first;
                    var labels=[];
                    allDays.sort(function(a,b){return a-b;}).forEach(function(d){if(labels.indexOf(preferenceDayNames[d])===-1)labels.push(preferenceDayNames[d]);});
                    employeesList[currentEmpId].schedule_preferred_days=labels.join(',');
                }
                $('.emp-popup-trigger[data-emp-id="'+currentEmpId+'"]').attr('data-days',employeesList[currentEmpId].schedule_preferred_days||'');
                if(typeof showToast==='function')showToast('Филиалы и предпочтения сохранены');
            }else if(typeof showToast==='function')showToast(res.message||'Не удалось сохранить предпочтения',true);
        },
        error:function(){if(typeof showToast==='function')showToast('Ошибка соединения при сохранении предпочтений',true);}
    });
}
$(document).on('click','#add-popup-filial-preference',function(e){e.preventDefault();$('#popup-filial-preferences').append(preferenceRowHtml({filial_id:'',days:[]}));});
$(document).on('change','.pref-filial-select',function(){
    var $select=$(this),next=String($select.val()||''),duplicate=false;
    if(next){$('.pref-filial-select').not(this).each(function(){if(String($(this).val()||'')===next)duplicate=true;});}
    if(duplicate){if(typeof showToast==='function')showToast('Этот филиал уже добавлен в список.',true);$select.val($select.data('previous')||'');return;}
    $select.data('previous',next);saveEmployeeFilialPreferences();
});
$(document).on('click','.pref-day-pill',function(e){
    e.preventDefault();var $pill=$(this);$pill.toggleClass('active');var collected=collectFilialPreferences();
    if(collected.error){$pill.toggleClass('active');if(typeof showToast==='function')showToast(collected.error,true);return;}
    saveEmployeeFilialPreferences();
});
$(document).on('click','.popup-remove-filial',function(e){
    e.preventDefault();$(this).closest('.popup-filial-preference-row').remove();
    if(!$('#popup-filial-preferences .popup-filial-preference-row').length)$('#popup-filial-preferences').append(preferenceRowHtml({filial_id:'',days:[]}));
    saveEmployeeFilialPreferences();
});

function showEmpPopup($trigger){
    var $popup=$('#emp-popup');
    isUpdatingPopup=true;
    currentEmpId=$trigger.data('emp-id');
    currentTrigger=$trigger;
    $popup.find('.emp-popup-name').text($trigger.attr('data-name'));
    var selectedEmployee=employeesList[currentEmpId]||{};
    renderEmployeeFilialPreferences(selectedEmployee);
    var rawPhone=String($trigger.attr('data-phone')||'').trim();
    var formattedPhone=formatPhone(rawPhone);
    var $phoneNumEl=$popup.find('.popup-phone-number');
    var $callBtn=$popup.find('.popup-call-btn');
    if(rawPhone!==''){$phoneNumEl.text(formattedPhone).attr('data-raw',rawPhone);
$callBtn.attr('data-phone',rawPhone).css('display','inline-flex');
}
    else{$phoneNumEl.text('Не указан').removeAttr('data-raw');
$callBtn.css('display','none');
}
    var bankVal=$trigger.attr('data-bank')||'none';
    $popup.find('.popup-bank').val(bankVal);
    var autoStatus=$trigger.attr('data-auto-status')||'dayoff';
    var state=$trigger.attr('data-state')||'normal';
    var $autoDisplay=$('#popup-auto-status-display');
    $autoDisplay.attr('class','popup-auto-status status-'+autoStatus);
    if(autoStatus==='shift')$autoDisplay.text('На смене');
    else if(autoStatus==='free')$autoDisplay.text('Свободен');
    else $autoDisplay.text('Выходной');
    $popup.find('.popup-state').val(state);
    var stateEnd=$trigger.attr('data-state-end')||'';
    $('.popup-date').val(stateEnd);
syncScheduleDateField($('.popup-date'));
    if(!stateEnd){$('.popup-date-undefined').prop('checked',true);
$('.popup-date').prop('disabled',true);
}
    else{$('.popup-date-undefined').prop('checked',false);
$('.popup-date').prop('disabled',false);
}
    var quitting=$trigger.attr('data-quitting')||'no';
    $popup.find('.popup-quitting').val(quitting);
    $popup.find('.popup-quitting-date').val($trigger.attr('data-quitting-date')||'');
    syncScheduleDateField($popup.find('.popup-quitting-date'));
    updateQuittingUI();
updateStateUI();
    $popup.find('.popup-comment').val($trigger.attr('data-comment')||'');
    var avatarUrl=$trigger.attr('data-avatar-url');
    var initial=$trigger.attr('data-initial');
    var $avatarWrap=$popup.find('.emp-popup-avatar-wrap');
    if (avatarUrl) {
        var $popupAvatar = $('<img alt="" loading="lazy" decoding="async">')
            .attr('data-avatar-src', avatarUrl)
            .attr('data-avatar-initial', initial || '?');
        $avatarWrap.empty().append($popupAvatar);
        employeeAvatarCache.register($popupAvatar[0], true);
    } else {
        $avatarWrap.html('<span>' + initial + '</span>');
    }
    $popup.addClass('active');
    positionEmpPopup($trigger,true);
    setTimeout(function(){isUpdatingPopup=false;positionEmpPopup($trigger,false);},50);
    setTimeout(function(){positionEmpPopup($trigger,false);},180);
}

$(window).on('scroll.empPopupPosition resize.empPopupPosition',function(){if($('#emp-popup').hasClass('active')&&currentTrigger&&currentTrigger.length)positionEmpPopup(currentTrigger,false);});
$(document).on('scroll.empPopupPosition','.table-scroll, .table-wrapper',function(){if($('#emp-popup').hasClass('active')&&currentTrigger&&currentTrigger.length)positionEmpPopup(currentTrigger,false);});
$(document).on('mouseenter','.emp-popup-trigger',function(){clearTimeout(popupTimeout);if(!isInteractingWithDate)showEmpPopup($(this));});
$(document).on('mouseleave','.emp-popup-trigger',function(){if(isInteractingWithDate)return;popupTimeout=setTimeout(function(){if(!isInteractingWithDate)$('#emp-popup').removeClass('active');},400);});
$(document).on('mouseenter','#emp-popup',function(){clearTimeout(popupTimeout);});
$(document).on('mouseleave','#emp-popup',function(){if(isInteractingWithDate)return;popupTimeout=setTimeout(function(){if(!isInteractingWithDate)$('#emp-popup').removeClass('active');},200);});
$(document).on('click',function(e){if(!$(e.target).closest('.emp-popup-trigger').length&&!$(e.target).closest('#emp-popup').length&&!isInteractingWithDate)$('#emp-popup').removeClass('active');});
$(document).on('click','.day-pill',function(e){e.preventDefault();e.stopPropagation();$(this).toggleClass('active');});
$(document).on('click','.shift-toggle',function(){var targetId=$(this).data('target');var $target=$('#'+targetId);$(this).toggleClass('open');$target.slideToggle(200);});
$('#statusSearchInput').on('keyup',function(){
    var query=$(this).val().toLowerCase();
    $('.status-group').each(function(){
        $(this).find('.status-item').each(function(){var name=$(this).find('.status-name').text().toLowerCase();if(name.indexOf(query)!==-1)$(this).removeClass('hidden');else $(this).addClass('hidden');});
        if($(this).find('.status-item:not(.hidden)').length>0)$(this).removeClass('hidden');else $(this).addClass('hidden');
    });
});

$(document).on('click','.call-btn',function(e){
    e.preventDefault();e.stopPropagation();
    var phone=String($(this).attr('data-phone')||'').trim();
    if(!phone)phone=String($(this).closest('.emp-cell, .status-name, .status-item').find('[data-phone]').first().attr('data-phone')||'').trim();
    if(phone)startFilialEmployeeCall(phone);
    else if(typeof showToast==='function')showToast('Номер телефона не указан в карточке сотрудника/филиала',true);
});
function startFilialEmployeeCall(phone){
    phone=String(phone||'').trim();
    if(!phone){if(typeof showToast==='function')showToast('Не указан номер',true);
return;
}
    if(window.innerWidth<=1200){window.location.href='tel:'+phone;
return;
}
    $.get('/telephone/call',{phone:phone},function(data){
        var response=data;
        if(typeof data==='string'){try{response=JSON.parse(data);}catch(parseError){response={status:'error',message:'АТС вернула некорректный ответ'};}}
        if(typeof showToast==='function')showToast(response.message||'Запрос на звонок отправлен',response.status!=='ok');
    }).fail(function(xhr){
        var message='Ошибка при инициировании звонка';
        if(xhr.responseText){try{var r=JSON.parse(xhr.responseText);if(r.message)message=r.message;}catch(e){}}
        if(typeof showToast==='function')showToast(message,true);
        if(window.console&&console.error)console.error('Ошибка звонка:',xhr.status,xhr.responseText);
    });
}

$(document).on('change','#restDatePicker',function(){
    var restDate=$(this).val();if(!restDate)return;
    var url=new URL(window.location.href);url.searchParams.set('rest_date',restDate);window.location.href=url.toString();
});
function getDataAttr(field){
    if(field==='schedule_is_quitting')return 'data-quitting';
    if(field==='schedule_preferred_days')return 'data-days';
    if(field==='schedule_primary_filial_id')return 'data-primary-filial';
    return 'data-'+field.replace('schedule_','').replace(/_/g,'-');
}
function saveEmployeeField(field,value){
    if(!currentEmpId||!currentTrigger||isUpdatingPopup)return;
    var dataAttr=getDataAttr(field);
    var currentVal=(employeesList[currentEmpId]&&typeof employeesList[currentEmpId][field]!=='undefined')?employeesList[currentEmpId][field]:(currentTrigger.attr(dataAttr)||'');
    if(currentVal==value)return;
    $.ajax({
        url:'<?= $this->createUrl("saveScheduleData") ?>',type:'POST',dataType:'json',
        data:{emp_id:currentEmpId,field:field,value:value,'<?= Yii::app()->request->csrfTokenName ?>':'<?= Yii::app()->request->csrfToken ?>'},
        success:function(res){
            if(res.status==='success'){
                if(typeof showToast==='function')showToast('Сохранено',false);
                $('.emp-popup-trigger[data-emp-id="'+currentEmpId+'"]').each(function(){$(this).attr(dataAttr,value);});
                if(employeesList[currentEmpId])employeesList[currentEmpId][field]=value;
            }else if(typeof showToast==='function')showToast(res.message||'Ошибка сохранения',true);
        },
        error:function(){if(typeof showToast==='function')showToast('Ошибка соединения',true);}
    });
}
$(document).on('change','.popup-bank',function(){saveEmployeeField('schedule_bank',$(this).val());});
$(document).on('change','.popup-state',function(){saveEmployeeField('schedule_state',$(this).val());});
$(document).on('change','.popup-date',function(){saveEmployeeField('schedule_state_end',$(this).val());});
$(document).on('change','.popup-quitting',function(){saveEmployeeField('schedule_is_quitting',$(this).val()==='yes'?'yes':'no');});
$(document).on('change','.popup-quitting-date',function(){saveEmployeeField('schedule_quitting_date',$(this).val());});
$(document).on('blur','.popup-comment',function(){saveEmployeeField('schedule_comment',$(this).val());});


$(function(){
    var $page=$('.schedule-page');
    if($page.data('view-type')!=='month')return;
    var year=parseInt($page.data('year'),10);
    var month=parseInt($page.data('month'),10);
    var today=scheduleGetBusinessToday();
    if(today.getFullYear()!==year||(today.getMonth()+1)!==month)return;
    var dayNum=today.getDate();
    setTimeout(function(){
        $('.table-scroll').each(function(){
            var containerEl=this;
            var $container=$(this);
            var $table=$container.find('table');
            if(!$table.length)return;
            var hasData=$table.find('tbody tr').filter(function(){return $(this).find('.day-cell, .filial-day').length>0;}).length>0;
            if(!hasData)return;
            var $target=$table.find('thead th').filter(function(){return $(this).find('.d-dt').text().trim()===String(dayNum);}).first();
            if(!$target.length)return;
            var stickyWidth=0;
            $table.find('thead th').each(function(){var $th=$(this);if($th.hasClass('col-role')||$th.hasClass('col-emp')||$th.hasClass('col-branch'))stickyWidth+=$th.outerWidth()||0;});
            var containerRect=containerEl.getBoundingClientRect();
            var targetRect=$target[0].getBoundingClientRect();
            var scrollLeft=targetRect.left-containerRect.left+containerEl.scrollLeft-stickyWidth-16;
            scrollLeft=Math.max(0,scrollLeft);
            if(typeof containerEl.scrollTo==='function')containerEl.scrollTo({left:scrollLeft,behavior:'smooth'});
            else containerEl.scrollLeft=scrollLeft;
        });
    },120);
});

(function(){
    var statusResizeObserver=null;
    var statusSyncTimer=null;
    var lastMeasuredHeight=0;
    function syncStatusPanelToFilials(){
        var leftCard=document.querySelector('.filials-main .card');
        var rightMain=document.querySelector('.rest-main');
        if(!leftCard||!rightMain)return;
        var rightCard=rightMain.querySelector('.card');
        var statusList=rightMain.querySelector('.status-container');
        if(!rightCard||!statusList)return;
        if(window.matchMedia&&window.matchMedia('(max-width: 900px)').matches){
            lastMeasuredHeight=0;
            rightMain.style.height='auto';rightMain.style.maxHeight='none';rightMain.style.overflow='visible';
            rightCard.style.height='auto';rightCard.style.maxHeight='none';rightCard.style.overflow='visible';
            statusList.style.height='auto';statusList.style.maxHeight='420px';statusList.style.flex='none';
            return;
        }
        var leftHeight=Math.ceil(leftCard.getBoundingClientRect().height);
        if(!leftHeight||leftHeight<80)return;
        if(Math.abs(leftHeight-lastMeasuredHeight)<1)return;
        lastMeasuredHeight=leftHeight;
        rightMain.style.height=leftHeight+'px';rightMain.style.maxHeight=leftHeight+'px';rightMain.style.minHeight='0';rightMain.style.overflow='hidden';
        rightCard.style.height='100%';rightCard.style.maxHeight='100%';rightCard.style.minHeight='0';rightCard.style.overflow='hidden';
        statusList.style.height='0';statusList.style.maxHeight='none';statusList.style.minHeight='0';statusList.style.flex='1 1 0';statusList.style.overflowY='auto';statusList.style.overflowX='hidden';
    }
    function scheduleStatusPanelSync(){if(statusSyncTimer)window.clearTimeout(statusSyncTimer);statusSyncTimer=window.setTimeout(syncStatusPanelToFilials,80);}
    $(window).on('load resize orientationchange',scheduleStatusPanelSync);
    $(function(){
        syncStatusPanelToFilials();
        window.setTimeout(syncStatusPanelToFilials,200);
        window.setTimeout(syncStatusPanelToFilials,700);
        var leftCard=document.querySelector('.filials-main .card');
        if(leftCard&&window.ResizeObserver){statusResizeObserver=new ResizeObserver(scheduleStatusPanelSync);statusResizeObserver.observe(leftCard);}
    });
})();


/*
 * Предпросмотр сохранённых изменений графика.
 * changeEmployeeAssignment вызывается только после успешного ответа saveCell,
 * поэтому неуспешные попытки редактирования в этот журнал не попадают.
 */
var schedulePreviewDays = <?= CJSON::encode(array_values($days)) ?>;
var scheduleChangeLog = {};
var schedulePreviewUiReady = false;

function schedulePreviewCopyPlaces(value) {
    return normalizePlaceList(value).slice(0);
}
function schedulePreviewSamePlaces(first, second) {
    var a = schedulePreviewCopyPlaces(first).map(String).sort();
    var b = schedulePreviewCopyPlaces(second).map(String).sort();
    return JSON.stringify(a) === JSON.stringify(b);
}
function schedulePreviewRecordChange(date, employeeId, beforePlaces, afterPlaces) {
    if (!isEditMode || !employeeId) return;
    var id = String(employeeId);
    if (!employeesList[id]) return;

    var before = schedulePreviewCopyPlaces(beforePlaces);
    var after = schedulePreviewCopyPlaces(afterPlaces);
    var employeeLog = scheduleChangeLog[id];

    if (!employeeLog) {
        employeeLog = { employeeId: id, dates: {} };
        scheduleChangeLog[id] = employeeLog;
    }

    if (!employeeLog.dates[date]) {
        employeeLog.dates[date] = {
            before: before,
            after: after
        };
    } else {
        employeeLog.dates[date].after = after;
    }

    var change = employeeLog.dates[date];
    if (schedulePreviewSamePlaces(change.before, change.after)) {
        delete employeeLog.dates[date];
    }

    if (!Object.keys(employeeLog.dates).length) {
        delete scheduleChangeLog[id];
    }

    schedulePreviewRefreshButton();
}
function schedulePreviewFormatDate(value) {
    var match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
    return match ? match[3] + '.' + match[2] + '.' + match[1] : String(value || '');
}
function schedulePreviewWeekday(value) {
    var match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!match) return '';
    var date = new Date(parseInt(match[1], 10), parseInt(match[2], 10) - 1, parseInt(match[3], 10));
    var weekdays = ['воскресенье', 'понедельник', 'вторник', 'среда', 'четверг', 'пятница', 'суббота'];
    return weekdays[date.getDay()];
}
function schedulePreviewBuildEmployeeMessage(employeeId) {
    var id = String(employeeId);
    var employee = employeesList[id] || {};
    var employeeLog = scheduleChangeLog[id];
    if (!employeeLog) return '';

    var firstName = String(employee.first_name || '').replace(/^\s+|\s+$/g, '');
    var lines = [
        'Здравствуйте' + (firstName ? ', ' + firstName : '') + '!',
        '',
        'В вашем графике произошли изменения. Новое расписание:'
    ];

    Object.keys(employeeLog.dates).sort().forEach(function (date) {
        var dayName = schedulePreviewWeekday(date);
        var newPlaces = schedulePreviewCopyPlaces(employeeLog.dates[date].after);
        var placeText = newPlaces.length
            ? schedulePreviewPlaceText(newPlaces)
            : 'Смена не назначена (выходной)';
        lines.push(schedulePreviewFormatDate(date) + (dayName ? ' (' + dayName + ')' : '') + ' — ' + placeText);
    });

    return lines.join('\n');
}
function schedulePreviewPlaceText(placeKeys) {
    var labels = [];
    $.each(schedulePreviewCopyPlaces(placeKeys), function (index, key) {
        var label = schedulePlaceNames[key] || String(key);
        if ($.inArray(label, labels) === -1) labels.push(label);
    });
    return labels.length ? labels.join(', ') : 'Нет назначений';
}
function schedulePreviewNotify(message) {
    if (typeof showToast === 'function') showToast(message);
    else window.alert(message);
}
function schedulePreviewEnsureUi() {
    if (!isEditMode || schedulePreviewUiReady) return;
    schedulePreviewUiReady = true;

    var css = [
        '#schedule-change-preview-trigger{position:fixed!important;right:22px!important;bottom:22px!important;z-index:100020!important;display:none;align-items:center;justify-content:center;gap:10px;min-height:50px;box-sizing:border-box;padding:0 19px;border:1px solid rgba(255,255,255,.18);border-radius:999px;background:linear-gradient(135deg,#7655c7 0%,#6244ad 100%);color:#fff;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;font-size:13px;font-weight:750;line-height:1;letter-spacing:.01em;white-space:nowrap;box-shadow:0 10px 28px rgba(66,45,126,.28),0 2px 5px rgba(35,24,70,.12);cursor:pointer;transition:transform .18s ease,box-shadow .18s ease,background .18s ease}',
        '#schedule-change-preview-trigger:hover{transform:translateY(-2px);background:linear-gradient(135deg,#6847b8 0%,#56389d 100%);box-shadow:0 14px 32px rgba(66,45,126,.34),0 3px 8px rgba(35,24,70,.14)}',
        '#schedule-change-preview-trigger:focus-visible{outline:3px solid rgba(118,85,199,.28);outline-offset:3px}',
        '#schedule-change-preview-trigger .schedule-preview-trigger-label{display:inline-flex;align-items:center;justify-content:center;min-height:24px;line-height:1}',
        '#schedule-change-preview-trigger .schedule-preview-count{display:inline-flex;flex:0 0 25px;align-items:center;justify-content:center;width:25px;min-width:25px;height:25px;margin:0 0 0 3px;padding:0;border:1px solid rgba(98,68,173,.12);border-radius:50%;background:#fff;color:#6244ad;font-size:11px;font-weight:800;line-height:1;font-variant-numeric:tabular-nums;vertical-align:middle}',
        '#schedule-change-preview-modal{padding:20px;background:rgba(13,18,35,.58);backdrop-filter:blur(7px)}',
        '#schedule-change-preview-modal .schedule-preview-modal-card{width:min(1040px,96vw);max-height:90vh;overflow:auto;padding:26px;border:1px solid rgba(230,232,242,.95);border-radius:20px;background:#f8f9fc;box-shadow:0 28px 80px rgba(15,23,42,.28)}',
        '#schedule-change-preview-modal .schedule-preview-modal-header{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:18px}',
        '#schedule-change-preview-modal .schedule-preview-eyebrow{margin:0 0 6px;color:#7655c7;font-size:10px;font-weight:850;letter-spacing:.13em;text-transform:uppercase}',
        '#schedule-change-preview-modal .schedule-modal-title{margin:0;color:#172033;font-size:23px;font-weight:850;line-height:1.2;letter-spacing:-.035em}',
        '#schedule-change-preview-modal .schedule-preview-intro{max-width:650px;margin:7px 0 0;color:#68758a;font-size:12.5px;line-height:1.55}',
        '#schedule-change-preview-modal .schedule-preview-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:0 0 18px}',
        '#schedule-change-preview-modal .schedule-preview-stat{display:flex;align-items:center;gap:12px;min-height:70px;padding:12px 15px;border:1px solid #e7e9f1;border-radius:13px;background:#fff;box-shadow:0 2px 7px rgba(28,38,66,.025)}',
        '#schedule-change-preview-modal .schedule-preview-stat-icon{display:flex;flex:0 0 38px;align-items:center;justify-content:center;width:38px;height:38px;border-radius:11px;background:#f1edfb;color:#6e4db8;font-size:17px;font-weight:800}',
        '#schedule-change-preview-modal .schedule-preview-stat-value{display:block;color:#1c2740;font-size:20px;font-weight:850;line-height:1.1;font-variant-numeric:tabular-nums}',
        '#schedule-change-preview-modal .schedule-preview-stat-label{display:block;margin-top:4px;color:#7b8799;font-size:10.5px;font-weight:650}',
        '#schedule-change-preview-modal .schedule-preview-section-label{display:flex;align-items:center;gap:9px;margin:4px 0 9px;color:#344054;font-size:12px;font-weight:800}',
        '#schedule-change-preview-modal .schedule-preview-section-label:after{height:1px;flex:1;background:#e7eaf1;content:""}',
        '#schedule-change-preview-modal .schedule-preview-employee{margin:0 0 12px;padding:17px;border:1px solid #e6e9f1;border-radius:15px;background:#fff;box-shadow:0 3px 12px rgba(28,38,66,.035);transition:border-color .18s ease,box-shadow .18s ease}',
        '#schedule-change-preview-modal .schedule-preview-employee:hover{border-color:#d8d2eb;box-shadow:0 7px 20px rgba(55,43,91,.07)}',
        '#schedule-change-preview-modal .schedule-preview-employee-head{display:flex;align-items:center;gap:13px;margin-bottom:15px}',
        '#schedule-change-preview-modal .schedule-preview-avatar-wrap{display:flex;flex:0 0 54px;align-items:center;justify-content:center;width:54px;height:54px;overflow:hidden;border:3px solid #f1edfb;border-radius:50%;background:#ebe7f5;box-shadow:0 0 0 1px #e5def4}',
        '#schedule-change-preview-modal .schedule-preview-avatar-wrap .avatar-image-frame{width:54px;height:54px;flex:0 0 54px;border-radius:50%}',
        '#schedule-change-preview-modal .schedule-preview-avatar-wrap > .schedule-preview-avatar-fallback{display:flex;align-items:center;justify-content:center;width:54px;height:54px;border:0;border-radius:50%;background:linear-gradient(135deg,#eae4f8,#f3f0fa);color:#6749aa;font-size:18px;font-weight:850}',
        '#schedule-change-preview-modal .schedule-preview-employee-info{min-width:0;flex:1}',
        '#schedule-change-preview-modal .schedule-preview-employee-name{display:block;overflow:hidden;color:#1e293b;font-size:14px;font-weight:850;line-height:1.35;text-overflow:ellipsis}',
        '#schedule-change-preview-modal .schedule-preview-employee-meta{display:block;margin-top:4px;color:#7b8799;font-size:11px;line-height:1.35}',
        '#schedule-change-preview-modal .schedule-preview-change-count{display:inline-flex;align-items:center;justify-content:center;margin-top:6px;padding:3px 8px;border:1px solid #e8e1f6;border-radius:999px;background:#f7f4fc;color:#6c4cae;font-size:10px;font-weight:800}',
        '#schedule-change-preview-modal .schedule-preview-send-one{display:inline-flex;flex:0 0 auto;align-items:center;justify-content:center;min-height:36px;padding:0 12px;border:1px solid #e3def0;border-radius:9px;background:#fff;color:#6547a9;font-size:11px;font-weight:750;white-space:nowrap;transition:background .15s ease,border-color .15s ease}',
        '#schedule-change-preview-modal .schedule-preview-send-one:hover{border-color:#cfc4e6;background:#f8f6fc}',
        '#schedule-change-preview-modal .schedule-preview-diff-title{margin:0 0 8px;color:#475467;font-size:11px;font-weight:850;letter-spacing:.04em;text-transform:uppercase}',
        '#schedule-change-preview-modal .schedule-preview-diff-head,#schedule-change-preview-modal .schedule-preview-diff-row{display:grid;grid-template-columns:100px minmax(0,1fr) minmax(0,1fr);gap:10px;align-items:center}',
        '#schedule-change-preview-modal .schedule-preview-diff-head{padding:0 10px 7px;color:#98a2b3;font-size:9px;font-weight:850;letter-spacing:.1em;text-transform:uppercase}',
        '#schedule-change-preview-modal .schedule-preview-diff-row{min-height:44px;padding:8px 10px;border-top:1px solid #f0f1f5;font-size:11px}',
        '#schedule-change-preview-modal .schedule-preview-diff-date{color:#475467;font-size:11px;font-weight:800;font-variant-numeric:tabular-nums}',
        '#schedule-change-preview-modal .schedule-preview-diff-value{min-width:0}',
        '#schedule-change-preview-modal .schedule-preview-mobile-label{display:none}',
        '#schedule-change-preview-modal .schedule-preview-place-pill{display:inline-flex;max-width:100%;align-items:center;padding:6px 9px;border-radius:8px;font-size:11px;font-weight:700;line-height:1.35;overflow-wrap:anywhere}',
        '#schedule-change-preview-modal .schedule-preview-old .schedule-preview-place-pill{background:#fff2f1;color:#a3443f}',
        '#schedule-change-preview-modal .schedule-preview-new .schedule-preview-place-pill{background:#edf8f1;color:#23784d}',
        '#schedule-change-preview-modal .schedule-preview-full-schedule{margin-top:14px;padding-top:12px;border-top:1px solid #edf0f5}',
        '#schedule-change-preview-modal .schedule-preview-full-schedule summary{display:flex;align-items:center;gap:8px;cursor:pointer;color:#6648aa;font-size:11px;font-weight:800;list-style:none}',
        '#schedule-change-preview-modal .schedule-preview-full-schedule summary::-webkit-details-marker{display:none}',
        '#schedule-change-preview-modal .schedule-preview-full-schedule summary:before{display:inline-flex;align-items:center;justify-content:center;width:21px;height:21px;border-radius:7px;background:#f1edfb;color:#694bb0;content:"+";font-size:15px;line-height:1}',
        '#schedule-change-preview-modal .schedule-preview-full-schedule[open] summary:before{content:"−"}',
        '#schedule-change-preview-modal .schedule-preview-days{display:grid;grid-template-columns:repeat(auto-fit,minmax(205px,1fr));gap:6px;margin-top:10px}',
        '#schedule-change-preview-modal .schedule-preview-day{display:flex;align-items:center;justify-content:space-between;gap:10px;min-height:33px;padding:7px 9px;border:1px solid #eef0f5;border-radius:8px;background:#fbfcfe;font-size:10.5px}',
        '#schedule-change-preview-modal .schedule-preview-day-date{flex:0 0 78px;color:#7b8799;font-size:10px;font-weight:750;font-variant-numeric:tabular-nums}',
        '#schedule-change-preview-modal .schedule-preview-day-place{min-width:0;text-align:right;color:#344054;font-weight:650;overflow-wrap:anywhere}',
        '#schedule-change-preview-modal .schedule-preview-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:18px;padding-top:16px;border-top:1px solid #e6e9f1}',
        '#schedule-change-preview-modal .schedule-preview-send-all{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:0 16px;border:1px solid #6c4bb4;border-radius:10px;background:linear-gradient(135deg,#7655c7,#6244ad);color:#fff;font-size:11px;font-weight:800;box-shadow:0 4px 11px rgba(99,70,169,.18)}',
        '#schedule-change-preview-modal .schedule-preview-send-all:hover{background:linear-gradient(135deg,#6747b8,#56399e)}',
        '#schedule-change-preview-modal .schedule-preview-close{min-height:40px;padding:0 15px;border:1px solid #e1e5ed;border-radius:10px;background:#fff;color:#475467;font-size:11px;font-weight:750}',
        '#schedule-change-preview-modal .schedule-preview-empty{padding:32px 18px;border:1px dashed #dfe3ec;border-radius:14px;background:#fff;text-align:center;color:#7b8799;font-size:12px}',
        '#schedule-change-preview-modal .schedule-preview-message{margin-top:14px;padding:12px;border:1px solid #e9eaf1;border-radius:11px;background:#fafbfe}',
        '#schedule-change-preview-modal .schedule-preview-message-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px}',
        '#schedule-change-preview-modal .schedule-preview-message-title{color:#475467;font-size:10px;font-weight:850;letter-spacing:.06em;text-transform:uppercase}',
        '#schedule-change-preview-modal .schedule-preview-copy-message{min-height:29px;padding:0 9px;border:1px solid #e2def0;border-radius:7px;background:#fff;color:#6547a9;font-size:10px;font-weight:750;cursor:pointer}',
        '#schedule-change-preview-modal .schedule-preview-message-text{display:block;width:100%;min-height:94px;margin:0;padding:10px 11px;resize:vertical;border:1px solid #e4e7ee;border-radius:8px;background:#fff;color:#344054;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:11px;line-height:1.65;white-space:pre-wrap}',
        '#schedule-change-preview-modal .schedule-preview-message-hint{margin:6px 0 0;color:#98a2b3;font-size:9.5px;line-height:1.4}',
        '@media(max-width:650px){#schedule-change-preview-trigger{right:12px!important;bottom:12px!important;min-height:46px;padding:0 14px;font-size:12px}#schedule-change-preview-modal{padding:8px}#schedule-change-preview-modal .schedule-preview-modal-card{width:100%;max-height:94vh;padding:17px;border-radius:16px}#schedule-change-preview-modal .schedule-preview-modal-header{margin-bottom:13px}#schedule-change-preview-modal .schedule-modal-title{font-size:20px}#schedule-change-preview-modal .schedule-preview-stats{gap:7px}#schedule-change-preview-modal .schedule-preview-stat{min-height:62px;padding:9px}#schedule-change-preview-modal .schedule-preview-stat-icon{width:32px;height:32px;flex-basis:32px}#schedule-change-preview-modal .schedule-preview-stat-value{font-size:17px}#schedule-change-preview-modal .schedule-preview-employee{padding:13px}#schedule-change-preview-modal .schedule-preview-employee-head{flex-wrap:wrap;gap:10px}#schedule-change-preview-modal .schedule-preview-employee-info{min-width:calc(100% - 78px)}#schedule-change-preview-modal .schedule-preview-send-one{margin-left:67px}#schedule-change-preview-modal .schedule-preview-diff-head{display:none}#schedule-change-preview-modal .schedule-preview-diff-row{grid-template-columns:1fr;gap:6px;padding:10px 2px}#schedule-change-preview-modal .schedule-preview-diff-value{display:flex;align-items:flex-start;gap:8px}#schedule-change-preview-modal .schedule-preview-mobile-label{display:inline-block;flex:0 0 46px;padding-top:5px;color:#98a2b3;font-size:9px;font-weight:850;text-transform:uppercase}#schedule-change-preview-modal .schedule-preview-place-pill{flex:1}#schedule-change-preview-modal .schedule-preview-footer{align-items:stretch;flex-direction:column}#schedule-change-preview-modal .schedule-preview-send-all,#schedule-change-preview-modal .schedule-preview-close{width:100%}}'
    ].join('');

    $('<style type="text/css" id="schedule-change-preview-style"></style>').text(css).appendTo('head');

    var $trigger = $('<button type="button" id="schedule-change-preview-trigger"><span class="schedule-preview-trigger-label">Посмотреть изменения</span><span class="schedule-preview-count">0</span></button>');
    $('body').append($trigger);

    var $modal = $('<div id="schedule-change-preview-modal" class="schedule-modal" aria-hidden="true" role="dialog" aria-modal="true"></div>');
    var $card = $('<div class="schedule-modal-card schedule-preview-modal-card"></div>');

    var $header = $('<div class="schedule-preview-modal-header"></div>');
    var $headerCopy = $('<div></div>');
    $headerCopy.append($('<div class="schedule-preview-eyebrow">Обновления графика</div>'));
    $headerCopy.append($('<h3 class="schedule-modal-title">Изменения в расписании</h3>'));
    $headerCopy.append($('<p class="schedule-preview-intro" id="schedule-preview-intro"></p>'));
    $header.append($headerCopy);
    $card.append($header);

    var $stats = $('<div class="schedule-preview-stats" id="schedule-preview-stats"></div>');
    $card.append($stats);
    $card.append($('<div class="schedule-preview-section-label">Сотрудники с изменениями</div>'));
    $card.append($('<div id="schedule-preview-employees"></div>'));

    var $actions = $('<div class="schedule-preview-footer"></div>');
    var $sendAll = $('<button type="button" class="schedule-preview-send-all">Отправить всем</button>');
    var $close = $('<button type="button" class="schedule-preview-close" id="schedule-preview-close">Закрыть</button>');
    $actions.append($sendAll, $close);
    $card.append($actions);
    $modal.append($card);
    $('body').append($modal);

    $trigger.on('click', function () {
        schedulePreviewRender();
        if (typeof rememberScheduleModalFocus === 'function') rememberScheduleModalFocus();
        $modal.addClass('active').attr('aria-hidden', 'false');
        $modal.find('#schedule-preview-close').trigger('focus');
    });
    $close.on('click', schedulePreviewClose);
    $modal.on('click', function (event) {
        if (event.target === this) schedulePreviewClose();
    });
    $sendAll.on('click', function () {
        var count = Object.keys(scheduleChangeLog).length;
        schedulePreviewNotify('Заглушка: отправка сообщения всем изменённым сотрудникам (' + count + ') будет подключена позже.');
    });
    $(document).on('click.scheduleChangePreview', '.schedule-preview-send-one', function () {
        var id = String($(this).attr('data-employee-id') || '');
        var employee = employeesList[id];
        var name = employee && employee.name ? employee.name : ('Сотрудник ID ' + id);
        schedulePreviewNotify('Заглушка: отправка сообщения сотруднику «' + name + '» будет подключена позже.');
    });
    $(document).on('click.scheduleChangePreview', '.schedule-preview-copy-message', function () {
        var id = String($(this).attr('data-employee-id') || '');
        var message = schedulePreviewBuildEmployeeMessage(id);
        if (message && typeof copyToClipboard === 'function') copyToClipboard(message);
        else if (message) schedulePreviewNotify('Не удалось скопировать текст сообщения.');
    });

    schedulePreviewRefreshButton();
}
function schedulePreviewClose() {
    $('#schedule-change-preview-modal').removeClass('active').attr('aria-hidden', 'true');
    if (typeof restoreScheduleModalFocus === 'function') restoreScheduleModalFocus();
}
function schedulePreviewRender() {
    schedulePreviewEnsureUi();
    var $list = $('#schedule-preview-employees').empty();
    var employeeIds = Object.keys(scheduleChangeLog);
    employeeIds.sort(function (a, b) {
        var nameA = employeesList[a] && employeesList[a].name ? employeesList[a].name : '';
        var nameB = employeesList[b] && employeesList[b].name ? employeesList[b].name : '';
        return String(nameA).localeCompare(String(nameB), 'ru');
    });

    var totalChangedDates = 0;
    $.each(employeeIds, function (index, employeeId) {
        totalChangedDates += Object.keys(scheduleChangeLog[employeeId].dates).length;
    });

    $('#schedule-preview-intro').text(
        employeeIds.length
            ? 'Проверьте сохранённые изменения и актуальное расписание сотрудников за выбранный месяц.'
            : 'В этом месяце пока нет сохранённых изменений.'
    );

    var $stats = $('#schedule-preview-stats').empty();
    var $employeeStat = $('<div class="schedule-preview-stat"></div>');
    $employeeStat.append($('<div class="schedule-preview-stat-icon">01</div>'));
    var $employeeStatText = $('<div></div>');
    $employeeStatText.append($('<span class="schedule-preview-stat-value"></span>').text(employeeIds.length));
    $employeeStatText.append($('<span class="schedule-preview-stat-label">Сотрудников затронуто</span>'));
    $employeeStat.append($employeeStatText);
    $stats.append($employeeStat);

    var $dateStat = $('<div class="schedule-preview-stat"></div>');
    $dateStat.append($('<div class="schedule-preview-stat-icon">02</div>'));
    var $dateStatText = $('<div></div>');
    $dateStatText.append($('<span class="schedule-preview-stat-value"></span>').text(totalChangedDates));
    $dateStatText.append($('<span class="schedule-preview-stat-label">Изменений по датам</span>'));
    $dateStat.append($dateStatText);
    $stats.append($dateStat);

    if (!employeeIds.length) {
        $list.append($('<div class="schedule-preview-empty">После сохранения изменений здесь появятся сотрудники, их новые смены и сравнение с предыдущим графиком.</div>'));
        return;
    }

    $.each(employeeIds, function (index, employeeId) {
        var employee = employeesList[employeeId] || {};
        var employeeName = employee.name || ('Сотрудник ID ' + employeeId);
        var employeeLog = scheduleChangeLog[employeeId];
        var changedDates = Object.keys(employeeLog.dates).sort();
        var initialSource = String(employee.first_name || employee.name || '?').replace(/^\s+|\s+$/g, '');
        var initial = initialSource ? initialSource.charAt(0).toLocaleUpperCase() : '?';

        var $employee = $('<section class="schedule-preview-employee"></section>');
        var $head = $('<div class="schedule-preview-employee-head"></div>');
        var $avatarWrap = $('<span class="emp-popup-avatar-wrap schedule-preview-avatar-wrap"></span>');

        if (employee.avatar) {
            var $avatar = $('<img alt="" loading="lazy" decoding="async">')
                .attr('data-avatar-src', avatarBaseUrl + String(employee.avatar))
                .attr('data-avatar-initial', initial);
            $avatarWrap.append($avatar);
        } else {
            $avatarWrap.append($('<span class="schedule-preview-avatar-fallback"></span>').text(initial));
        }
        $head.append($avatarWrap);

        var $employeeInfo = $('<div class="schedule-preview-employee-info"></div>');
        $employeeInfo.append($('<span class="schedule-preview-employee-name"></span>').text(employeeName));
        if (employee.post) {
            $employeeInfo.append($('<span class="schedule-preview-employee-meta"></span>').text(String(employee.post)));
        } else {
            $employeeInfo.append($('<span class="schedule-preview-employee-meta"></span>').text('Изменён график работы'));
        }
        $employeeInfo.append($('<span class="schedule-preview-change-count"></span>').text(changedDates.length + (changedDates.length === 1 ? ' изменение' : ' изменений')));
        $head.append($employeeInfo);

        var $sendButton = $('<button type="button" class="schedule-preview-send-one">Отправить сообщение</button>')
            .attr('data-employee-id', employeeId);
        $head.append($sendButton);
        $employee.append($head);

        $employee.append($('<div class="schedule-preview-diff-title">Что изменилось</div>'));
        $employee.append($('<div class="schedule-preview-diff-head"><span>Дата</span><span>Было</span><span>Стало</span></div>'));

        $.each(changedDates, function (dateIndex, date) {
            var diff = employeeLog.dates[date];
            var $row = $('<div class="schedule-preview-diff-row"></div>');
            $row.append($('<div class="schedule-preview-diff-date"></div>').text(schedulePreviewFormatDate(date)));

            var $before = $('<div class="schedule-preview-diff-value schedule-preview-old"></div>');
            $before.append($('<span class="schedule-preview-mobile-label">Было</span>'));
            $before.append($('<span class="schedule-preview-place-pill"></span>').text(schedulePreviewPlaceText(diff.before)));
            $row.append($before);

            var $after = $('<div class="schedule-preview-diff-value schedule-preview-new"></div>');
            $after.append($('<span class="schedule-preview-mobile-label">Стало</span>'));
            $after.append($('<span class="schedule-preview-place-pill"></span>').text(schedulePreviewPlaceText(diff.after)));
            $row.append($after);

            $employee.append($row);
        });

        var messageText = schedulePreviewBuildEmployeeMessage(employeeId);
        var $messageBlock = $('<div class="schedule-preview-message"></div>');
        var $messageHead = $('<div class="schedule-preview-message-head"></div>');
        $messageHead.append($('<span class="schedule-preview-message-title">Текст сообщения для сотрудника</span>'));
        var $copyMessage = $('<button type="button" class="schedule-preview-copy-message">Копировать текст</button>')
            .attr('data-employee-id', employeeId);
        $messageHead.append($copyMessage);
        $messageBlock.append($messageHead);
        var messageRows = Math.max(4, Math.min(10, messageText.split('\n').length + 1));
        var $messageTextarea = $('<textarea class="schedule-preview-message-text" readonly spellcheck="false"></textarea>')
            .attr('aria-label', 'Текст сообщения для ' + employeeName)
            .attr('rows', messageRows)
            .val(messageText);
        $messageBlock.append($messageTextarea);
        $messageBlock.append($('<p class="schedule-preview-message-hint">Это предварительный текст. Кнопка отправки пока не подключена; другой разработчик сможет использовать этот шаблон для бота.</p>'));
        $employee.append($messageBlock);

        var $details = $('<details class="schedule-preview-full-schedule"></details>');
        var workDayCount = 0;
        $.each(schedulePreviewDays, function (dayIndex, date) {
            var dayMap = assignMap[date] || {};
            if (normalizePlaceList(dayMap[employeeId] || dayMap[String(employeeId)] || []).length) workDayCount++;
        });

        $details.append($('<summary></summary>').text('Полный график за месяц · ' + workDayCount + ' смен'));
        var $days = $('<div class="schedule-preview-days"></div>');
        $.each(schedulePreviewDays, function (dayIndex, date) {
            var dayMap = assignMap[date] || {};
            var places = normalizePlaceList(dayMap[employeeId] || dayMap[String(employeeId)] || []);
            var $day = $('<div class="schedule-preview-day"></div>');
            $day.append($('<span class="schedule-preview-day-date"></span>').text(schedulePreviewFormatDate(date)));
            $day.append($('<span class="schedule-preview-day-place"></span>').text(schedulePreviewPlaceText(places)));
            $days.append($day);
        });
        $details.append($days);
        $employee.append($details);
        $list.append($employee);

        var $avatarImage = $employee.find('.schedule-preview-avatar-wrap img[data-avatar-src]').first();
        if ($avatarImage.length) employeeAvatarCache.register($avatarImage[0], true);
    });
}
function schedulePreviewRefreshButton() {
    if (!isEditMode) return;
    schedulePreviewEnsureUi();
    var count = Object.keys(scheduleChangeLog).length;
    $('#schedule-change-preview-trigger')
        .css('display', count > 0 ? 'inline-flex' : 'none')
        .find('.schedule-preview-count').text(count);
    if ($('#schedule-change-preview-modal').hasClass('active')) schedulePreviewRender();
}

function normalizePlaceList(value){if(!value)return[];
return $.isArray(value)?value:[value];
}
function isEmployeeBusyOnDate(date,employeeId,allowedPlaceKey){
    if(!assignMap[date]||!assignMap[date][employeeId])return false;
    var places=normalizePlaceList(assignMap[date][employeeId]);
    for(var i=0;i<places.length;i++){if(places[i]!==allowedPlaceKey)return true;
}
    return false;
}
function changeEmployeeAssignment(date,employeeId,placeKey,add){
    if(!employeeId)return;
    if(!assignMap[date])assignMap[date]={};
    var places=normalizePlaceList(assignMap[date][employeeId]).slice(0);
    var next=[];
    for(var i=0;i<places.length;i++){if(places[i]!==placeKey)next.push(places[i]);
}
    if(add)next.push(placeKey);
    if(next.length)assignMap[date][employeeId]=next;
else delete assignMap[date][employeeId];
    schedulePreviewRecordChange(date,employeeId,places,next);
    refreshEmployeeWorkplaceCell(date,employeeId);
}
function employeePrimaryFilialText(emp){
    var names=[];
    var prefs=emp&&emp.schedule_filial_preferences?emp.schedule_filial_preferences:{};
    if(typeof prefs==='string'){try{prefs=JSON.parse(prefs);
}catch(e){prefs={};
}}
    $.each(prefs,function(fid,days){if(filialsList[fid]&&names.indexOf(filialsList[fid])===-1)names.push(filialsList[fid]);});
    if(!names.length){var id=emp&&emp.schedule_primary_filial_id?String(emp.schedule_primary_filial_id):'';
if(id&&filialsList[id])names.push(filialsList[id]);
}
    return names.length?names.join(', '):'не указан';
}
function employeeStatusText(emp){
    if(!emp)return '';
    if(emp.schedule_state==='sick')return 'Болеет';
    if(emp.schedule_state==='vacation')return 'В отпуске';
    return 'Состояние: в норме';
}
function renderFilialCell($td,employeeId){
    var emp=employeeId&&employeesList[employeeId]?employeesList[employeeId]:null;
    $td.empty();
    if(emp){var $name=$('<span class="filial-name">').text(emp.name||'');
$td.append($name);
}
    else{$td.append('<span class="empty-mark">—</span>');
}
    var comment=String($td.attr('data-comment')||'');
    if(comment)$('<span class="schedule-note-indicator" tabindex="0" aria-label="Комментарий к ячейке">').attr('data-note',comment).text('!').appendTo($td);
    if(isEditMode)$('<button type="button" class="edit-comment-btn" title="Изменить комментарий">✎</button>').appendTo($td);
    $td.toggleClass('empty-cell',!emp).attr('data-value',employeeId||'').data('value',employeeId||'').attr('title',emp?emp.name:'');
}
function positionEmployeeDropdown($input,$dropdown){
    var rect=$input[0].getBoundingClientRect();
    var width=Math.max(250,Math.min(340,Math.max(rect.width,290)));
    var left=Math.max(8,Math.min(rect.left,window.innerWidth-width-8));
    var maxHeight=Math.min(300,window.innerHeight-rect.bottom-12);
    var top=rect.bottom+4;
    if(maxHeight<130&&rect.top>170){maxHeight=Math.min(300,rect.top-12);
top=Math.max(8,rect.top-maxHeight-4);
}
    $dropdown.css({left:left,top:top,width:width,maxHeight:Math.max(130,maxHeight)}).show();
}
function renderEmployeeSearchList(query,$input,$dropdown,date,currentEmployeeId,currentPlaceKey){
    $dropdown.empty();
    positionEmployeeDropdown($input,$dropdown);
    query=String(query||'').toLowerCase();
    var found=false;
    if(query===''){$('<li data-id="">— (пусто)</li>').appendTo($dropdown);
found=true;
}
    $.each(employeesList,function(id,emp){
        if(!emp||parseInt(emp.active,10)===0)return;
        var name=String(emp.name||'');
        var primaryFilial=employeePrimaryFilialText(emp);
        var phone=String(emp.phone||'');
        var metaSearch=(name+' '+primaryFilial).toLowerCase();
        if(metaSearch.indexOf(query)===-1)return;
        found=true;
        var busy=isEmployeeBusyOnDate(date,id,currentPlaceKey);
        var $li=$('<li class="employee-option">').attr('data-id',id);
        if(busy&&String(id)!==String(currentEmployeeId))$li.addClass('disabled');
        if (emp.avatar) {
            var $searchAvatar = $('<img class="employee-option-avatar" alt="" loading="lazy" decoding="async">')
                .attr('data-avatar-src', avatarBaseUrl + emp.avatar)
                .attr('data-avatar-initial', (name || '?').substring(0, 1))
                .appendTo($li);
            employeeAvatarCache.register($searchAvatar[0], true);
        }
        else $('<span class="employee-option-avatar employee-option-avatar-fallback">').text((name||'?').substring(0,1)).appendTo($li);
        var $content=$('<span class="employee-option-content">').appendTo($li);
        $('<span class="employee-option-name">').text(name+((busy&&String(id)!==String(currentEmployeeId))?' — занят':'')).appendTo($content);
        $('<span class="employee-option-meta">').text('Основной филиал: '+primaryFilial+(phone?' · '+phone:'')).appendTo($content);
        $('<span class="employee-option-meta">').text(employeeStatusText(emp)+(emp.schedule_preferred_days?' · Дни: '+String(emp.schedule_preferred_days).replace(/,/g,', '):'')).appendTo($content);
        if(emp.schedule_comment)$('<span class="employee-option-meta">').text('Комментарий: '+emp.schedule_comment).appendTo($content);
        if(busy&&String(id)!==String(currentEmployeeId))$('<span class="employee-option-meta employee-option-busy">').text('Уже назначен на другую точку в этот день').appendTo($content);
        $dropdown.append($li);
    });
    if(!found&&query!=='')$('<li class="disabled">Не найдено</li>').appendTo($dropdown);
    positionEmployeeDropdown($input,$dropdown);
}

function positionScheduleNoteTooltip($marker){
    var $tip=$('#schedule-note-tooltip');
if(!$tip.length||!$marker||!$marker.length)return;
    var rect=$marker[0].getBoundingClientRect();
    var tipW=$tip.outerWidth();
var tipH=$tip.outerHeight();
    var left=Math.max(12,Math.min(rect.left+rect.width/2-tipW/2,window.innerWidth-tipW-12));
    var top=rect.bottom+8;
    if(top+tipH>window.innerHeight-10)top=Math.max(10,rect.top-tipH-8);
    $tip.css({left:left+'px',top:top+'px'});
}
$(document).on('click','.schedule-note-indicator',function(e){e.preventDefault();e.stopPropagation();});
$(document).on('mouseenter focus','.schedule-note-indicator',function(){var note=String($(this).attr('data-note')||'');if(!note)return;var $tip=$('#schedule-note-tooltip');$tip.text(note).addClass('is-visible');positionScheduleNoteTooltip($(this));});
$(document).on('mouseleave blur','.schedule-note-indicator',function(){$('#schedule-note-tooltip').removeClass('is-visible');});
$(window).on('scroll resize',function(){var $marker=$('.schedule-note-indicator:hover').first();if($marker.length)positionScheduleNoteTooltip($marker);else $('#schedule-note-tooltip').removeClass('is-visible');});

$(document).on('click','.edit-comment-btn',function(e){
    e.preventDefault();e.stopPropagation();
    var $td=$(this).closest('td.filial-day');
    editingCommentCell=$td;
    $('#schedule-comment-input').val($td.attr('data-comment')||'');
    rememberScheduleModalFocus();
    $('#schedule-comment-modal').addClass('active').attr('aria-hidden','false');
    setTimeout(function(){$('#schedule-comment-input').trigger('focus');},50);
});
function closeScheduleCommentModal(){$('#schedule-comment-modal').removeClass('active').attr('aria-hidden','true');
editingCommentCell=null;
restoreScheduleModalFocus();
}
$('#cancelScheduleComment').on('click',closeScheduleCommentModal);
$('#schedule-comment-modal').on('click',function(e){if(e.target===this)closeScheduleCommentModal();});
$('#saveScheduleComment').on('click',function(){
    if(!editingCommentCell||!editingCommentCell.length)return;
    var $btn=$(this);var $td=editingCommentCell;var comment=$('#schedule-comment-input').val();
    $btn.prop('disabled',true).text('Сохранение...');
    $.ajax({
        url:'<?= $this->createUrl("saveScheduleComment") ?>',type:'POST',dataType:'json',
        data:{filial_id:$td.data('filial'),date:$td.data('date'),comment:comment,'<?= Yii::app()->request->csrfTokenName ?>':'<?= Yii::app()->request->csrfToken ?>'},
        success:function(res){
            if(res.status==='success'){$td.attr('data-comment',res.comment||'');renderFilialCell($td,$td.data('value'));if(typeof showToast==='function')showToast(res.comment?'Комментарий сохранён':'Комментарий удалён');closeScheduleCommentModal();}
            else if(typeof showToast==='function')showToast(res.message||'Не удалось сохранить комментарий',true);
        },
        error:function(){if(typeof showToast==='function')showToast('Ошибка соединения при сохранении комментария',true);},
        complete:function(){$btn.prop('disabled',false).text('Сохранить');}
    });
});

$(document).on('click','.edit-cell',function(e){
    if(!isEditMode)return;
    if($(e.target).closest('.edit-comment-btn').length)return;
    e.stopPropagation();
    var $td=$(this);
    if($td.find('input, select').length)return;
    var type=$td.data('type');
    var value=String($td.data('value')||'');
    var date=String($td.data('date')||'');
    if(type==='filial'){
        var filialId=$td.data('filial');
        var currentPlaceKey='filial_'+filialId;
        var $input=$('<input type="text" placeholder="Поиск сотрудника..." class="cell-edit-select">');
        $input.val(value&&employeesList[value]?employeesList[value].name:'');
        var $dropdown=$('<ul class="search-dropdown"></ul>');
        $td.html($input);$('body').append($dropdown);
        renderEmployeeSearchList('',$input,$dropdown,date,value,currentPlaceKey);
        $input.trigger('focus').trigger('select');
        $input.on('input keyup',function(){renderEmployeeSearchList($(this).val(),$input,$dropdown,date,value,currentPlaceKey);});
        $(window).on('scroll.scheduleEmployeePicker resize.scheduleEmployeePicker',function(){if($dropdown.is(':visible')&&$input.length)positionEmployeeDropdown($input,$dropdown);});
        function cleanupEmployeePicker(){$dropdown.remove();$input.remove();$(window).off('.scheduleEmployeePicker');}
        $input.on('blur',function(){setTimeout(function(){if($dropdown.is(':visible')){cleanupEmployeePicker();renderFilialCell($td,value);}},180);});
        $dropdown.on('mousedown','li',function(e){
            e.preventDefault();
            if($(this).hasClass('disabled'))return;
            var newValue=String($(this).attr('data-id')||'');
            cleanupEmployeePicker();
            if(newValue===value){renderFilialCell($td,value);return;}
            if(newValue&&isEmployeeBusyOnDate(date,newValue,currentPlaceKey)){if(typeof showToast==='function')showToast('Сотрудник уже назначен на другую точку в этот день',true);renderFilialCell($td,value);return;}
            $td.addClass('saving-cell').html('<span class="filial-name">Сохранение...</span>');
            $.ajax({
                url:'<?= $this->createUrl("saveCell") ?>',type:'POST',dataType:'json',
                data:{type:'filial',filial_id:filialId,date:date,value:newValue,'<?= Yii::app()->request->csrfTokenName ?>':'<?= Yii::app()->request->csrfToken ?>'},
                success:function(res){
                    if(res.status==='success'){
                        if(value)changeEmployeeAssignment(date,value,currentPlaceKey,false);
                        if(newValue)changeEmployeeAssignment(date,newValue,currentPlaceKey,true);
                        renderFilialCell($td,newValue);
                        if(typeof showToast==='function')showToast('Сохранено');
                    }else{renderFilialCell($td,value);if(typeof showToast==='function')showToast(res.message||'Не удалось сохранить ячейку',true);}
                },
                error:function(){renderFilialCell($td,value);if(typeof showToast==='function')showToast('Ошибка сервера при сохранении ячейки',true);},
                complete:function(){$td.removeClass('saving-cell');}
            });
        });
    }else if(type==='department'){
        var departmentId=$td.data('dep');
        var employeeId=String($td.data('emp-id')||'');
        var currentDepartmentKey='dep_'+departmentId;
        if(!employeeId){if(typeof showToast==='function')showToast('Сначала назначьте сотрудника в этой строке отдела',true);return;}
        var newValue=(value==='1')?'0':'1';
        if(newValue==='1'&&isEmployeeBusyOnDate(date,employeeId,currentDepartmentKey)){if(typeof showToast==='function')showToast('Сотрудник уже назначен на другую точку в этот день',true);return;}
        $td.addClass('saving-cell');
        $.ajax({
            url:'<?= $this->createUrl("saveCell") ?>',type:'POST',dataType:'json',
            data:{type:'department',dep_id:departmentId,date:date,value:newValue,'<?= Yii::app()->request->csrfTokenName ?>':'<?= Yii::app()->request->csrfToken ?>'},
            success:function(res){
                if(res.status==='success'){$td.data('value',newValue).attr('data-value',newValue);$td.find('.check-wrapper').toggle(newValue==='1');$td.find('.empty-wrapper').toggle(newValue==='0');changeEmployeeAssignment(date,employeeId,currentDepartmentKey,newValue==='1');if(typeof showToast==='function')showToast('Сохранено');}
                else if(typeof showToast==='function')showToast(res.message||'Не удалось сохранить ячейку',true);
            },
            error:function(){if(typeof showToast==='function')showToast('Ошибка сервера при сохранении ячейки',true);},
            complete:function(){$td.removeClass('saving-cell');}
        });
    }
});


<?php if ($isAdmin): ?>
var departmentModalMode='add';
var departmentModalRowId=null;
var departmentModalKey='';
var departmentEmployees=<?= CJSON::encode($employees) ?>;
function openDepartmentRowModal(mode,departmentKey,row){
    departmentModalMode=mode;
    departmentModalRowId=row?parseInt(row.id,10):null;
    departmentModalKey=departmentKey;
    var $select=$('#department-row-employee').empty();
    $('<option></option>').val('').text('Выберите сотрудника').appendTo($select);
    $select.prop('required',true);
    var currentEmployeeId=row&&row.employee_id?String(row.employee_id):'';
    $.each(departmentEmployees,function(id,emp){
        if(!emp)return;
        var inactive=parseInt(emp.active,10)===0;
        if(inactive&&String(id)!==currentEmployeeId)return;
        $('<option></option>').val(id).text(emp.name+(inactive?' — неактивен':'')).appendTo($select);
    });
    $('#department-row-role').val(row?row.role:'');
    $select.val(currentEmployeeId);
    $('#department-row-modal-title').text(mode==='edit'?'Изменить должность или сотрудника':'Добавить сотрудника в отдел');
    $('#saveDepartmentRow').prop('disabled',false).removeAttr('disabled').text('Сохранить');
    rememberScheduleModalFocus();
    $('#department-row-modal').addClass('active').attr('aria-hidden','false');
    setTimeout(function(){$('#department-row-role').trigger('focus');},60);
}
function closeDepartmentRowModal(){$('#department-row-modal').removeClass('active').attr('aria-hidden','true');
restoreScheduleModalFocus();
}
$(document).on('click','.dept-add-row-btn',function(){openDepartmentRowModal('add',$(this).attr('data-department'),null);});
$(document).on('click','.dept-row-edit-btn',function(){
    var $tr=$(this).closest('tr');
    openDepartmentRowModal('edit',$(this).closest('.card[data-department]').attr('data-department'),{
        id:$tr.attr('data-id'),role:$tr.attr('data-role')||$tr.find('.col-role').text().trim(),employee_id:$tr.attr('data-employee-id')||''
    });
});
$('#cancelDepartmentRow').on('click',closeDepartmentRowModal);
$('#department-row-modal').on('click',function(e){if(e.target===this)closeDepartmentRowModal();});
$('#saveDepartmentRow').on('click',function(){
    var role=$.trim($('#department-row-role').val());
    var employeeId=$('#department-row-employee').val();
    if(!role){showToast('Укажите должность.',true);$('#department-row-role').trigger('focus');return;}
    if(!employeeId){showToast('Выберите сотрудника. Чтобы убрать человека из отдела, используйте удаление строки.',true);$('#department-row-employee').trigger('focus');return;}
    var page=$('#schedulePage');
    var payload={department:departmentModalKey,year:page.data('year'),month:page.data('month'),role:role,employee_id:employeeId,'<?= Yii::app()->request->csrfTokenName ?>':'<?= Yii::app()->request->csrfToken ?>'};
    var url='<?= $this->createUrl("addDepartmentRow") ?>';
    if(departmentModalMode==='edit'){payload.id=departmentModalRowId;url='<?= $this->createUrl("saveDepartmentRow") ?>';}
    var $btn=$(this).prop('disabled',true).text('Сохранение...');
    $.ajax({url:url,type:'POST',dataType:'json',data:payload,
        success:function(res){if(res.status==='success'){closeDepartmentRowModal();showToast(departmentModalMode==='edit'?'Изменения отдела сохранены':'Сотрудник добавлен в отдел');setTimeout(function(){location.reload();},400);}else{showToast(res.message||'Не удалось сохранить отдел',true);}},
        error:function(xhr){
            var message='Сервер не смог сохранить изменения отдела.';
            if(xhr&&xhr.responseJSON&&xhr.responseJSON.message)message=xhr.responseJSON.message;
            else if(xhr&&xhr.responseText){var raw=String(xhr.responseText).replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim();if(raw&&raw.length<260)message+=' Ответ: '+raw;else if(xhr.status)message+=' Код HTTP: '+xhr.status;}
            showToast(message,true);
            if(window.console&&console.error)console.error('saveDepartmentRow failed:',xhr&&xhr.status,xhr&&xhr.responseText);
        },
        complete:function(){$btn.prop('disabled',false).text('Сохранить');}
    });
});
$(document).on('click','.dept-row-delete-btn',function(){
    var $btn=$(this),$tr=$btn.closest('tr'),id=$tr.attr('data-id');
    var role=$tr.attr('data-role')||$tr.find('.col-role').text().trim();
    if(!confirm('Удалить строку «'+role+'» из отдела вместе с её сменами? Перед удалением будет создана резервная копия.'))return;
    if(prompt('Для второго подтверждения введите УДАЛИТЬ')!=='УДАЛИТЬ')return;
    $btn.prop('disabled',true);
    $.ajax({url:'<?= $this->createUrl("deleteRow") ?>',type:'POST',dataType:'json',data:{id:id,confirmation:'УДАЛИТЬ','<?= Yii::app()->request->csrfTokenName ?>':'<?= Yii::app()->request->csrfToken ?>'},
        success:function(res){if(res.status==='success'){$tr.fadeOut(180,function(){$(this).remove();});showToast('Строка отдела удалена. Резервная копия сохранена.');}else{showToast(res.message||'Не удалось удалить строку',true);$btn.prop('disabled',false);}},
        error:function(){$btn.prop('disabled',false);showToast('Ошибка соединения при удалении строки',true);}
    });
});
<?php endif;
?>

function currentSchedulePeriodPayload(){
    var $page=$('#schedulePage');
    return {year:$page.data('year'),month:$page.data('month'),view_type:$page.data('view-type'),start_date:$page.data('start-date')||''};
}
$('#clearSchedulePeriodBtn').on('click',function(){
    var period=currentSchedulePeriodPayload();
    var periodLabel=period.view_type==='day'?'выбранный день':(period.view_type==='week'?'выбранную неделю':'выбранный месяц');
    if(!confirm('Очистить все назначения сотрудников и комментарии за '+periodLabel+'? Перед очисткой будет создана резервная копия.'))return;
    var phrase=prompt('Для подтверждения полного удаления данных за период введите ОЧИСТИТЬ');
    if(phrase!=='ОЧИСТИТЬ')return;
    var $btn=$(this).prop('disabled',true).text('Очистка...');
    $.ajax({url:'<?= $this->createUrl("clearSchedulePeriod") ?>',type:'POST',dataType:'json',data:$.extend(period,{'<?= Yii::app()->request->csrfTokenName ?>':'<?= Yii::app()->request->csrfToken ?>'}),
        success:function(res){if(res.status==='success'){if(typeof showToast==='function')showToast('График очищен. Резервная копия сохранена.');setTimeout(function(){location.reload();},650);}else if(typeof showToast==='function')showToast(res.message||'Не удалось очистить график',true);},
        error:function(){if(typeof showToast==='function')showToast('Ошибка соединения при очистке',true);},
        complete:function(){$btn.prop('disabled',false).text('Очистить выбранный период');}
    });
});
$('#autoFillScheduleBtn').on('click',function(){
    var period=currentSchedulePeriodPayload();
    if(!confirm('Автоматически назначить сотрудников по их филиалам и предпочитаемым дням за выбранный период? Существующие смены не будут перезаписаны. Перед заполнением создастся резервная копия.'))return;
    var $btn=$(this).prop('disabled',true).text('Заполнение...');
    $.ajax({url:'<?= $this->createUrl("autoFillSchedule") ?>',type:'POST',dataType:'json',data:$.extend(period,{'<?= Yii::app()->request->csrfTokenName ?>':'<?= Yii::app()->request->csrfToken ?>'}),
        success:function(res){
            if(res.status==='success'){
                var msg='Добавлено: '+res.inserted+' · Уже назначено: '+(res.already_scheduled||0)+' · Конфликт с другой сменой: '+(res.skipped_conflicts||0)+' · Создано строк офиса/цеха: '+(res.created_department_rows||0)+' · Восстановлено строк: '+(res.reactivated_department_rows||0)+' · Нет строки отдела: '+(res.missing_department_row||0)+' · Статус: '+(res.skipped_status||0)+' · Филиал занят: '+(res.occupied_target||0);
                if(typeof showToast==='function')showToast(msg);
                setTimeout(function(){location.reload();},3200);
            }else if(typeof showToast==='function')showToast(res.message||'Автозаполнение не выполнено',true);
        },
        error:function(){if(typeof showToast==='function')showToast('Ошибка соединения при автозаполнении',true);},
        complete:function(){$btn.prop('disabled',false).text('Автозаполнить по предпочтениям');}
    });
});

$(document).on('click','.delete-schedule-backup',function(){
    var $btn=$(this);var backupId=$btn.attr('data-backup-id');
    if(!confirm('Вы действительно уверены, что хотите навсегда удалить эту резервную копию? Восстановить её после удаления будет невозможно.'))return;
    if(prompt('Для окончательного удаления введите УДАЛИТЬ')!=='УДАЛИТЬ')return;
    $btn.prop('disabled',true).text('Удаление...');
    $.ajax({url:'<?= $this->createUrl("deleteScheduleBackup") ?>',type:'POST',dataType:'json',data:{backup_id:backupId,'<?= Yii::app()->request->csrfTokenName ?>':'<?= Yii::app()->request->csrfToken ?>'},
        success:function(res){if(res.status==='success'){$btn.closest('.backup-list-item').fadeOut(180,function(){$(this).remove();if(!$('#schedule-backup-list .backup-list-item').length)$('#schedule-backup-list').html('<div class="backup-empty">Сохранённых версий не осталось.</div>');});if(typeof showToast==='function')showToast('Резервная копия удалена');}else{if(typeof showToast==='function')showToast(res.message||'Не удалось удалить резервную копию',true);$btn.prop('disabled',false).text('Удалить');}},
        error:function(){if(typeof showToast==='function')showToast('Ошибка соединения при удалении резервной копии',true);$btn.prop('disabled',false).text('Удалить');}
    });
});
$('#deleteAllScheduleBackupsBtn').on('click',function(){
    if(!confirm('Вы действительно уверены, что хотите удалить ВСЕ резервные копии за ВСЕ месяцы? Это действие необратимо. Сам график не изменится.'))return;
    if(prompt('Для окончательного подтверждения введите: УДАЛИТЬ ВСЕ БЭКАПЫ')!=='УДАЛИТЬ ВСЕ БЭКАПЫ')return;
    var $btn=$(this).prop('disabled',true).text('Удаление всех копий...');
    $.ajax({url:'<?= $this->createUrl("deleteAllScheduleBackups") ?>',type:'POST',dataType:'json',data:{confirmation:'УДАЛИТЬ ВСЕ БЭКАПЫ','<?= Yii::app()->request->csrfTokenName ?>':'<?= Yii::app()->request->csrfToken ?>'},
        success:function(res){
            if(res.status==='success'){$('#schedule-backup-list').html('<div class="backup-empty">Все резервные копии удалены.</div>');if(typeof showToast==='function')showToast('Удалено резервных копий: '+(res.deleted||0));$btn.prop('disabled',true).text('Все копии удалены');}
            else{if(typeof showToast==='function')showToast(res.message||'Не удалось удалить резервные копии',true);$btn.prop('disabled',false).text('Удалить ВСЕ резервные копии');}
        },
        error:function(){if(typeof showToast==='function')showToast('Ошибка соединения при удалении резервных копий',true);$btn.prop('disabled',false).text('Удалить ВСЕ резервные копии');}
    });
});

$('#copyPrevMonthBtn').on('click',function(){
    var month=$('#schedulePage').data('month');var year=$('#schedulePage').data('year');
    if(!confirm('Скопировать график прошлого месяца в '+month+'.'+year+'? Текущая версия будет сохранена и её можно будет восстановить.'))return;
    var $btn=$(this);$btn.prop('disabled',true).text('Копирование...');
    $.ajax({url:'<?= $this->createUrl("copyMonthWithBackup") ?>',type:'POST',dataType:'json',data:{year:year,month:month,'<?= Yii::app()->request->csrfTokenName ?>':'<?= Yii::app()->request->csrfToken ?>'},
        success:function(res){if(res.status==='success'){if(typeof showToast==='function')showToast('График скопирован. Предыдущая версия сохранена.');setTimeout(function(){location.reload();},700);}else if(typeof showToast==='function')showToast(res.message||'Ошибка копирования',true);},
        error:function(){if(typeof showToast==='function')showToast('Ошибка соединения',true);},
        complete:function(){$btn.prop('disabled',false).text('Скопировать прошлый месяц');}
    });
});

function loadScheduleBackups(){
    var $list=$('#schedule-backup-list');
    $list.html('<div class="backup-empty">Загрузка истории...</div>');
    rememberScheduleModalFocus();
    $('#backup-history-modal').addClass('active').attr('aria-hidden','false');
    setTimeout(function(){$('#closeBackupHistory').trigger('focus');},40);
    $.ajax({
        url:'<?= $this->createUrl("getScheduleBackups") ?>',type:'GET',dataType:'json',
        data:{year:$('#schedulePage').data('year'),month:$('#schedulePage').data('month')},
        success:function(res){
            $list.empty();
            if(res.status!=='success'||!res.backups||!res.backups.length){$list.html('<div class="backup-empty">Для этого месяца ещё нет сохранённых версий. Резервная копия создаётся автоматически перед копированием прошлого месяца.</div>');return;}
            $.each(res.backups,function(i,backup){
                var $item=$('<div class="backup-list-item"></div>');
                var $info=$('<div></div>').appendTo($item);
                $('<strong></strong>').text('Версия от '+backup.backup_date).appendTo($info);
                $('<small></small>').text('Месяц: '+backup.month+'.'+backup.year+' · ID '+backup.id).appendTo($info);
                var $actions=$('<div class="backup-actions"></div>').appendTo($item);
                $('<button type="button" class="mode-btn restore-schedule-backup">Восстановить</button>').attr('data-backup-id',backup.id).appendTo($actions);
                $('<button type="button" class="mode-btn delete-schedule-backup">Удалить</button>').attr('data-backup-id',backup.id).appendTo($actions);
                var typeLabels={copy_month:'Перед копированием месяца',before_restore:'Перед восстановлением',before_clear:'Перед очисткой периода',before_auto_fill:'Перед автозаполнением',before_department_edit:'Перед изменением сотрудника/должности',before_department_add:'Перед добавлением сотрудника в отдел',before_department_delete:'Перед удалением строки отдела',before_legacy_edit:'Перед сохранением старого редактора',manual:'Резервная копия'};
                $('<small></small>').text(typeLabels[backup.backup_type]||'Резервная копия').appendTo($info);
                $list.append($item);
            });
        },
        error:function(){$list.html('<div class="backup-empty">Не удалось загрузить историю версий.</div>');}
    });
}
$('#showBackupHistoryBtn').on('click',loadScheduleBackups);
$('#closeBackupHistory').on('click',closeBackupHistoryModal);
$('#backup-history-modal').on('click',function(e){if(e.target===this)closeBackupHistoryModal();});
$(document).on('click','.restore-schedule-backup',function(){
    var backupId=$(this).attr('data-backup-id');
    if(!confirm('Восстановить эту версию? Текущий график выбранного месяца будет заменён данными резервной копии.'))return;
    var $btn=$(this);$btn.prop('disabled',true).text('Восстановление...');
    $.ajax({url:'<?= $this->createUrl("restoreScheduleBackup") ?>',type:'POST',dataType:'json',data:{backup_id:backupId,'<?= Yii::app()->request->csrfTokenName ?>':'<?= Yii::app()->request->csrfToken ?>'},
        success:function(res){if(res.status==='success'){if(typeof showToast==='function')showToast('Версия графика восстановлена');setTimeout(function(){location.reload();},650);}else{if(typeof showToast==='function')showToast(res.message||'Не удалось восстановить версию',true);$btn.prop('disabled',false).text('Восстановить');}},
        error:function(){if(typeof showToast==='function')showToast('Ошибка соединения при восстановлении',true);$btn.prop('disabled',false).text('Восстановить');}
    });
});


var scheduleModalReturnFocus=null;
function rememberScheduleModalFocus(){
    var active=document.activeElement;
    if(active&&active!==document.body&&!$(active).closest('.schedule-modal, #avatar-modal').length)scheduleModalReturnFocus=active;
}
function restoreScheduleModalFocus(){
    var target=scheduleModalReturnFocus;
    scheduleModalReturnFocus=null;
    if(target&&document.documentElement.contains(target)&&!$(target).is(':disabled'))window.setTimeout(function(){try{target.focus();}catch(ignore){}},0);
}
function closeBackupHistoryModal(){$('#backup-history-modal').removeClass('active').attr('aria-hidden','true');
restoreScheduleModalFocus();
}
function closeAvatarModal() {
    $('#avatar-modal').css('display', 'none');
    var modalImage = document.querySelector('#avatar-modal img');
    if (modalImage) {
        modalImage.onload = null;
        modalImage.onerror = null;
        modalImage.removeAttribute('src');
        modalImage.removeAttribute('data-avatar-src');
        modalImage.removeAttribute('data-avatar-ready');
        modalImage.removeAttribute('data-avatar-state');
    }
    $('#avatar-modal .avatar-image-frame').removeClass('is-loaded has-error');
    restoreScheduleModalFocus();
}
function closeTopScheduleOverlay(){
    if($('#schedule-calendar-popover').is(':visible')){closeScheduleCalendar();
return true;
}
    if($('#avatar-modal').is(':visible')){closeAvatarModal();
return true;
}
    var $activeModal=$('.schedule-modal.active').last();
    if($activeModal.length){
        if($activeModal.attr('id')==='department-row-modal'&&typeof closeDepartmentRowModal==='function')closeDepartmentRowModal();
        else if($activeModal.attr('id')==='schedule-comment-modal'&&typeof closeScheduleCommentModal==='function')closeScheduleCommentModal();
        else if($activeModal.attr('id')==='backup-history-modal')closeBackupHistoryModal();
        else{$activeModal.removeClass('active').attr('aria-hidden','true');
restoreScheduleModalFocus();
}
        return true;
    }
    var $dropdown=$('.search-dropdown:visible').last();
    if($dropdown.length){
        var $input=$('.cell-edit-select:visible').last();
        var $cell=$input.closest('td.edit-cell');
        $dropdown.remove();
        if($cell.length&&typeof renderFilialCell==='function'){$cell.attr('data-value',$cell.attr('data-value')||'');
renderFilialCell($cell,$cell.attr('data-value')||'');
$cell.trigger('focus');
}
        else if($input.length)$input.remove();
        $(window).off('.scheduleEmployeePicker');
        return true;
    }
    if($('#emp-popup').hasClass('active')){$('#emp-popup').removeClass('active');
return true;
}
    $('#schedule-note-tooltip').removeClass('is-visible');
    return false;
}
function getModalFocusable($modal){
    return $modal.find('a[href], area[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), iframe, object, embed, [tabindex]:not([tabindex="-1"])').filter(':visible');
}
$(function(){
    $('.schedule-page .schedule-table td.edit-cell').attr('tabindex','0');
    $('.schedule-modal').attr({'role':'dialog','aria-modal':'true'});
    $('#avatar-modal').attr({'role':'dialog','aria-modal':'true','aria-label':'Просмотр фотографии','tabindex':'-1'});
});
$(document).on('keydown.scheduleKeyboard',function(e){
    var key=e.key||'';
    var code=e.which||e.keyCode;
    var isEscape=key==='Escape'||code===27;
    var isEnter=key==='Enter'||code===13;
    var isSpace=key===' '||code===32;
    var isTab=key==='Tab'||code===9;
    if(isEscape){if(closeTopScheduleOverlay()){e.preventDefault();e.stopPropagation();}return;}
    var $modal=$('.schedule-modal.active').last();
    if($modal.length){
        if(isTab){
            var $focusable=getModalFocusable($modal);
            if($focusable.length){
                var first=$focusable[0];var last=$focusable[$focusable.length-1];
                if(e.shiftKey&&(document.activeElement===first||!$modal[0].contains(document.activeElement))){e.preventDefault();last.focus();}
                else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}
            }else{e.preventDefault();$modal.find('.schedule-modal-card').attr('tabindex','-1').trigger('focus');}
            return;
        }
        if(isEnter&&$modal.attr('id')==='department-row-modal'){
            if(!$(e.target).is('button, a')){e.preventDefault();$('#saveDepartmentRow').trigger('click');}
            return;
        }
        if(isEnter&&$modal.attr('id')==='schedule-comment-modal'&&(e.ctrlKey||e.metaKey)){e.preventDefault();$('#saveScheduleComment').trigger('click');return;}
    }
    var $cell=$(e.target).closest('td.edit-cell');
    var isFormControl=$(e.target).is('input, select, textarea, button, a, [contenteditable="true"]');
    if($cell.length&&!isFormControl&&!$('.schedule-modal.active').length){
        if(isEnter||isSpace){e.preventDefault();$cell.trigger('click');return;}
        if(key==='ArrowLeft'||code===37||key==='ArrowRight'||code===39){
            var $rowCells=$cell.parent().children('td.edit-cell');
            var currentIndex=$rowCells.index($cell);
            var targetIndex=(key==='ArrowLeft'||code===37)?currentIndex-1:currentIndex+1;
            if(targetIndex>=0&&targetIndex<$rowCells.length){e.preventDefault();$rowCells.eq(targetIndex).trigger('focus');}
            return;
        }
        if(key==='ArrowUp'||code===38||key==='ArrowDown'||code===40){
            var $row=$cell.closest('tr');
            var $tableRows=$row.closest('tbody').children('tr');
            var rowIndex=$tableRows.index($row);
            var columnIndex=$row.children('td.edit-cell').index($cell);
            var targetRowIndex=(key==='ArrowUp'||code===38)?rowIndex-1:rowIndex+1;
            if(targetRowIndex>=0&&targetRowIndex<$tableRows.length){
                var $targetCells=$tableRows.eq(targetRowIndex).children('td.edit-cell');
                if($targetCells.length){e.preventDefault();$targetCells.eq(Math.min(columnIndex,$targetCells.length-1)).trigger('focus');}
            }
            return;
        }
    }
});
(function(){
    var style='.schedule-page .schedule-table td.edit-cell:focus{outline:2px solid #7568df;outline-offset:-2px;box-shadow:inset 0 0 0 2px rgba(117,104,223,.10)}'+
        '.schedule-modal button:focus,.schedule-modal input:focus,.schedule-modal select:focus,.schedule-modal textarea:focus{outline:2px solid rgba(99,91,219,.55);outline-offset:2px}'+
        '#avatar-modal:focus{outline:0}';
    $('<style type="text/css"></style>').text(style).appendTo('head');
})();

var scheduleCalendarTarget=null;
var scheduleCalendarMonth=scheduleGetBusinessToday();
var scheduleCalendarMonths=['Январь','Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь'];
var scheduleCalendarWeekdays=['Пн','Вт','Ср','Чт','Пт','Сб','Вс'];
function scheduleParseISO(value){
    var m=String(value||'').match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if(!m)return null;
    var d=new Date(parseInt(m[1],10),parseInt(m[2],10)-1,parseInt(m[3],10));
    return (d.getFullYear()===parseInt(m[1],10)&&d.getMonth()===parseInt(m[2],10)-1&&d.getDate()===parseInt(m[3],10))?d:null;
}
function scheduleGetBusinessToday(){
    var now=new Date();
    return new Date(now.getFullYear(),now.getMonth(),now.getDate());
}
function refreshScheduleTodayHighlight(){
    var today=scheduleFormatISO(scheduleGetBusinessToday());
    var page=document.getElementById('schedulePage');
    if(page)page.setAttribute('data-today',today);
    $('.schedule-table').each(function(){
        var $table=$(this);
        var $headers=$table.find('thead th.day-head');
        $headers.removeClass('today-head');
        $table.find('tbody td.today-cell').removeClass('today-cell');
        $headers.each(function(){
            var $head=$(this);
            if($head.attr('data-date')!==today)return;
            $head.addClass('today-head');
            var columnIndex=$head.index();
            $table.find('tbody tr').each(function(){$(this).children('td').eq(columnIndex).addClass('today-cell');});
        });
    });
}
function scheduleFormatISO(date){return date.getFullYear()+'-'+String(date.getMonth()+1).padStart(2,'0')+'-'+String(date.getDate()).padStart(2,'0');
}
function scheduleFormatDisplay(value){var d=scheduleParseISO(value);
return d?String(d.getDate()).padStart(2,'0')+'.'+String(d.getMonth()+1).padStart(2,'0')+'.'+d.getFullYear():'Выберите дату';
}
function syncScheduleDateField($input){
    if(!$input||!$input.length)return;
    var $button=$input.data('scheduleDateButton');
    if(!$button||!$button.length)return;
    var value=String($input.val()||'');
    var $label=$button.find('.schedule-date-label');
    $label.text(scheduleFormatDisplay(value)).toggleClass('schedule-date-placeholder',!value);
    $button.prop('disabled',$input.prop('disabled'));
}
function initScheduleDateFields(){
    $('input[type="date"]').each(function(){
        var $input=$(this);
        if($input.data('scheduleDateReady'))return;
        $input.data('scheduleDateReady',true);
        $input.attr('type','hidden').addClass('schedule-date-native');
        var $field=$('<div class="schedule-date-field"></div>');
        $input.wrap($field);
        var $button=$('<button type="button" class="schedule-date-trigger"><span class="schedule-date-icon"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="16" rx="3"></rect><path d="M16 3v4M8 3v4M3 10h18"></path></svg></span><span class="schedule-date-label"></span><span class="schedule-date-chevron">⌄</span></button>');
        $input.after($button);
        $input.data('scheduleDateButton',$button);
        $input.on('change.scheduleDate input.scheduleDate',function(){syncScheduleDateField($input);});
        $button.on('click',function(e){e.preventDefault();e.stopPropagation();openScheduleCalendar($input,$button);});
        syncScheduleDateField($input);
    });
}
function ensureScheduleCalendar(){
    var $calendar=$('#schedule-calendar-popover');
    if($calendar.length)return $calendar;
    $calendar=$('<div id="schedule-calendar-popover" class="schedule-calendar-popover" role="dialog" aria-label="Выбор даты"></div>');
    $('body').append($calendar);
    return $calendar;
}
function positionScheduleCalendar(){
    if(!scheduleCalendarTarget)return;
    var $calendar=ensureScheduleCalendar();
    var button=scheduleCalendarTarget.data('scheduleDateButton');
    if(!button||!button.length)return;
    var rect=button[0].getBoundingClientRect();
    var width=Math.min(292,window.innerWidth-20);
    var left=Math.max(10,Math.min(rect.left,window.innerWidth-width-10));
    var height=$calendar.outerHeight()||360;
    var top=rect.bottom+7;
    if(top+height>window.innerHeight-10&&rect.top>height+16)top=rect.top-height-7;
    top=Math.max(10,Math.min(top,window.innerHeight-height-10));
    $calendar.css({left:left+'px',top:top+'px',width:width+'px'});
}
function renderScheduleCalendar(){
    var $calendar=ensureScheduleCalendar();
    var year=scheduleCalendarMonth.getFullYear();
    var month=scheduleCalendarMonth.getMonth();
    var selected=scheduleCalendarTarget?scheduleParseISO(scheduleCalendarTarget.val()):null;
    var today=scheduleGetBusinessToday();
    var first=new Date(year,month,1);
    var offset=(first.getDay()+6)%7;
    var daysInMonth=new Date(year,month+1,0).getDate();
    var html='<div class="schedule-calendar-head"><div class="schedule-calendar-caption">'+scheduleCalendarMonths[month]+' '+year+'</div><div class="schedule-calendar-nav"><button type="button" class="schedule-calendar-prev" aria-label="Предыдущий месяц">‹</button><button type="button" class="schedule-calendar-next" aria-label="Следующий месяц">›</button></div></div><div class="schedule-calendar-weekdays">';
    for(var w=0;w<7;w++)html+='<div class="schedule-calendar-weekday">'+scheduleCalendarWeekdays[w]+'</div>';
    html+='</div><div class="schedule-calendar-grid">';
    for(var blank=0;blank<offset;blank++)html+='<span class="schedule-calendar-empty"></span>';
    for(var day=1;day<=daysInMonth;day++){
        var current=new Date(year,month,day);
        var iso=scheduleFormatISO(current);
        var classes=['schedule-calendar-day'];
        if(current.getDay()===0||current.getDay()===6)classes.push('is-weekend');
        if(current.getFullYear()===today.getFullYear()&&current.getMonth()===today.getMonth()&&current.getDate()===today.getDate())classes.push('is-today');
        if(selected&&selected.getFullYear()===year&&selected.getMonth()===month&&selected.getDate()===day)classes.push('is-selected');
        html+='<button type="button" class="'+classes.join(' ')+'" data-iso="'+iso+'">'+day+'</button>';
    }
    var total=offset+daysInMonth;
    var trailing=(7-(total%7))%7;
    for(var tail=0;tail<trailing;tail++)html+='<span class="schedule-calendar-empty"></span>';
    html+='</div><div class="schedule-calendar-footer"><button type="button" class="schedule-calendar-clear">Очистить</button><button type="button" class="schedule-calendar-today">Сегодня</button></div>';
    $calendar.html(html).show();
    positionScheduleCalendar();
}
function openScheduleCalendar($input,$button){
    scheduleCalendarTarget=$input;
    var selected=scheduleParseISO($input.val());
    var base=selected||scheduleGetBusinessToday();
    scheduleCalendarMonth=new Date(base.getFullYear(),base.getMonth(),1);
    isInteractingWithDate=true;
    renderScheduleCalendar();
}
function closeScheduleCalendar(){$('#schedule-calendar-popover').hide();
scheduleCalendarTarget=null;
isInteractingWithDate=false;
}
function applyScheduleCalendarDate(value){
    if(!scheduleCalendarTarget||!scheduleCalendarTarget.length)return;
    var $input=scheduleCalendarTarget;
    $input.val(value).trigger('input').trigger('change');
    syncScheduleDateField($input);
    closeScheduleCalendar();
}
$(document).on('click','.schedule-calendar-prev',function(e){e.preventDefault();e.stopPropagation();scheduleCalendarMonth.setMonth(scheduleCalendarMonth.getMonth()-1);renderScheduleCalendar();});
$(document).on('click','.schedule-calendar-next',function(e){e.preventDefault();e.stopPropagation();scheduleCalendarMonth.setMonth(scheduleCalendarMonth.getMonth()+1);renderScheduleCalendar();});
$(document).on('click','.schedule-calendar-day',function(e){e.preventDefault();e.stopPropagation();applyScheduleCalendarDate($(this).attr('data-iso'));});
$(document).on('click','.schedule-calendar-today',function(e){e.preventDefault();e.stopPropagation();applyScheduleCalendarDate(scheduleFormatISO(scheduleGetBusinessToday()));});
$(document).on('click','.schedule-calendar-clear',function(e){e.preventDefault();e.stopPropagation();applyScheduleCalendarDate('');});
$(document).on('click.scheduleCalendarOutside',function(e){if(!$('#schedule-calendar-popover').is(':visible'))return;if($(e.target).closest('#schedule-calendar-popover, .schedule-date-trigger').length)return;closeScheduleCalendar();});
$(window).on('resize scroll',function(){if($('#schedule-calendar-popover').is(':visible'))positionScheduleCalendar();});
$(document).on('keydown.scheduleCalendar',function(e){
    if(!$('#schedule-calendar-popover').is(':visible'))return;
    var key=e.key||'';var code=e.which||e.keyCode;
    if(key==='Escape'||code===27){e.preventDefault();e.stopPropagation();var button=scheduleCalendarTarget?scheduleCalendarTarget.data('scheduleDateButton'):null;closeScheduleCalendar();if(button&&button.length)button.trigger('focus');}
});
$(function () {
    refreshScheduleTodayHighlight();
    initScheduleDateFields();
    employeeAvatarCache.init();
});

</script>
