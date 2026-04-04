<?php
/**
 * =====================================================
 * PUANTAJ SİSTEMİ V2 - 4 HAFTALIK DÖNGÜ DESTEKLİ
 * Tüm çalışanlar varsayılan MEVCUT olarak işaretlenir
 * 4 haftalık döngü sonra otomatik tekrar eder
 * =====================================================
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
session_start();

// =====================================================
// DB BAĞLANTI
// =====================================================
$db_host = 'sql211.infinityfree.com';
$db_name = 'if0_40197167_puantaj';
$db_user = 'if0_40197167';
$db_pass = 'Aeg151851';

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user, $db_pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
} catch (PDOException $e) {
    die('<div style="text-align:center;padding:50px;font-family:sans-serif;"><h2>Veritabanı Bağlantı Hatası</h2><p>'.htmlspecialchars($e->getMessage()).'</p></div>');
}

// Admin şifresi hash kontrolü
$stmt = $pdo->prepare("SELECT password FROM users WHERE username = 'admin'");
$stmt->execute();
$ar = $stmt->fetch();
if ($ar && !password_verify('Aeg151851', $ar['password'])) {
    $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'")->execute([password_hash('Aeg151851', PASSWORD_DEFAULT)]);
}

// =====================================================
// YARDIMCI FONKSİYONLAR
// =====================================================
function isLoggedIn() { return isset($_SESSION['user_id']); }
function isAdmin() { return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'; }
function currentUserId() { return $_SESSION['user_id'] ?? 0; }
function redirect($p) { header("Location: ?page=$p"); exit; }
function sanitize($s) { return htmlspecialchars(trim($s ?? ''), ENT_QUOTES, 'UTF-8'); }
function setFlash($t, $m) { $_SESSION['flash'] = ['type'=>$t, 'message'=>$m]; }
function getFlash() { if (isset($_SESSION['flash'])) { $f=$_SESSION['flash']; unset($_SESSION['flash']); return $f; } return null; }
function getSetting($k, $d='') { global $pdo; $s=$pdo->prepare("SELECT setting_value FROM settings WHERE setting_key=?"); $s->execute([$k]); $r=$s->fetch(); return $r ? $r['setting_value'] : $d; }
function formatDate($d) { return $d ? date('d.m.Y', strtotime($d)) : '-'; }
function formatTime($t) { return $t ? date('H:i', strtotime($t)) : '-'; }

function turkishMonth($m) {
    $a = [1=>'Ocak',2=>'Şubat',3=>'Mart',4=>'Nisan',5=>'Mayıs',6=>'Haziran',7=>'Temmuz',8=>'Ağustos',9=>'Eylül',10=>'Ekim',11=>'Kasım',12=>'Aralık'];
    return $a[(int)$m] ?? '';
}
function turkishDay($d) {
    $a = ['Monday'=>'Pazartesi','Tuesday'=>'Salı','Wednesday'=>'Çarşamba','Thursday'=>'Perşembe','Friday'=>'Cuma','Saturday'=>'Cumartesi','Sunday'=>'Pazar'];
    return $a[$d] ?? $d;
}
function turkishDayShort($d) {
    $a = ['Monday'=>'Pt','Tuesday'=>'Sa','Wednesday'=>'Ça','Thursday'=>'Pe','Friday'=>'Cu','Saturday'=>'Ct','Sunday'=>'Pa'];
    return $a[$d] ?? $d;
}

function getLeaveTypeText($t) {
    $a = ['annual'=>'Yıllık İzin','sick'=>'Hastalık İzni','unpaid'=>'Ücretsiz İzin','maternity'=>'Doğum İzni','marriage'=>'Evlilik İzni','bereavement'=>'Vefat İzni','other'=>'Diğer'];
    return $a[$t] ?? $t;
}
function getStatusText($s) {
    $a = ['present'=>'Mevcut','absent'=>'Devamsız','leave'=>'İzinli','sick'=>'Hasta','holiday'=>'Tatil','half_day'=>'Yarım Gün'];
    return $a[$s] ?? $s;
}
function getStatusBadge($s) {
    $c = ['present'=>'bg-success','absent'=>'bg-danger','leave'=>'bg-orange','sick'=>'bg-warning text-dark','holiday'=>'bg-secondary','half_day'=>'bg-primary','pending'=>'bg-warning text-dark','approved'=>'bg-success','rejected'=>'bg-danger','cancelled'=>'bg-secondary'];
    $cls = $c[$s] ?? 'bg-secondary';
    $txt = getStatusText($s);
    if (in_array($s, ['pending','approved','rejected','cancelled'])) {
        $lt = ['pending'=>'Beklemede','approved'=>'Onaylı','rejected'=>'Reddedildi','cancelled'=>'İptal'];
        $txt = $lt[$s] ?? $s;
    }
    return "<span class='badge $cls'>$txt</span>";
}
function getStatusSymbol($s) {
    $a = ['present'=>'✓','absent'=>'✗','leave'=>'İ','sick'=>'H','holiday'=>'T','half_day'=>'½'];
    return $a[$s] ?? '?';
}
function getStatusBg($s) {
    $a = ['present'=>'bg-success bg-opacity-25','absent'=>'bg-danger bg-opacity-25','leave'=>'bg-orange bg-opacity-25','sick'=>'bg-warning bg-opacity-25','holiday'=>'bg-secondary bg-opacity-25','half_day'=>'bg-primary bg-opacity-25'];
    return $a[$s] ?? '';
}

function calcWorkHours($start, $end, $brk = 0) {
    if (!$start || !$end) return 0;
    $diff = (strtotime($end) - strtotime($start)) / 3600 - ($brk / 60);
    return max(0, round($diff, 2));
}

// =====================================================
// 4 HAFTALIK DÖNGÜ FONKSİYONLARI
// =====================================================

/**
 * Verilen tarih için döngüdeki gün indeksini hesapla (0-27)
 */
function getCycleDayIndex($dateStr) {
    global $pdo;
    $stmt = $pdo->query("SELECT cycle_start_date FROM cycle_config ORDER BY id LIMIT 1");
    $row = $stmt->fetch();
    $cycleStart = $row ? $row['cycle_start_date'] : '2025-01-06';
    
    $start = new DateTime($cycleStart);
    $target = new DateTime($dateStr);
    $diff = (int)$start->diff($target)->format('%r%a');
    $cycleLen = 28; // 4 hafta
    $index = $diff % $cycleLen;
    if ($index < 0) $index += $cycleLen;
    return $index;
}

/**
 * Bir çalışan için belirli bir tarihin durumunu belirle:
 * 1. Önce work_records tablosunda manuel kayıt var mı bak
 * 2. Yoksa work_templates tablosunda şablon var mı bak
 * 3. Yoksa varsayılan: Hafta içi = present, Haftasonu = holiday
 */
function getDayStatus($userId, $dateStr, &$recordsCache = null, &$templatesCache = null) {
    global $pdo;
    
    $defStart = getSetting('work_start_time', '08:00');
    $defEnd = getSetting('work_end_time', '17:00');
    $defBreak = (int)getSetting('break_duration', '60');
    
    // 1. Manuel kayıt kontrolü
    if ($recordsCache !== null && isset($recordsCache[$userId][$dateStr])) {
        return $recordsCache[$userId][$dateStr];
    }
    if ($recordsCache === null) {
        $stmt = $pdo->prepare("SELECT * FROM work_records WHERE user_id=? AND work_date=?");
        $stmt->execute([$userId, $dateStr]);
        $rec = $stmt->fetch();
        if ($rec) return $rec;
    }
    
    // 2. Şablon kontrolü
    $dayIndex = getCycleDayIndex($dateStr);
    if ($templatesCache !== null && isset($templatesCache[$userId][$dayIndex])) {
        $tpl = $templatesCache[$userId][$dayIndex];
        return [
            'status' => $tpl['status'],
            'start_time' => $tpl['start_time'],
            'end_time' => $tpl['end_time'],
            'break_minutes' => $tpl['break_minutes'],
            'overtime_minutes' => 0,
            'unit_id' => $tpl['unit_id'],
            'notes' => $tpl['notes'],
            'source' => 'template'
        ];
    }
    if ($templatesCache === null) {
        $stmt = $pdo->prepare("SELECT * FROM work_templates WHERE user_id=? AND day_index=?");
        $stmt->execute([$userId, $dayIndex]);
        $tpl = $stmt->fetch();
        if ($tpl) {
            return [
                'status' => $tpl['status'],
                'start_time' => $tpl['start_time'],
                'end_time' => $tpl['end_time'],
                'break_minutes' => $tpl['break_minutes'],
                'overtime_minutes' => 0,
                'unit_id' => $tpl['unit_id'],
                'notes' => $tpl['notes'],
                'source' => 'template'
            ];
        }
    }
    
    // 3. Ulusal tatil kontrolü (holidays tablosu)
    static $nationalHolidays = null;
    if ($nationalHolidays === null) {
        try {
            $nationalHolidays = $pdo->query("SELECT date FROM holidays")->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            $nationalHolidays = [];
        }
    }
    if (in_array($dateStr, $nationalHolidays)) {
        return [
            'status'           => 'holiday',
            'start_time'       => null,
            'end_time'         => null,
            'break_minutes'    => 0,
            'overtime_minutes' => 0,
            'unit_id'          => null,
            'notes'            => null,
            'source'           => 'holiday'
        ];
    }

    // 4. Varsayılan: Hafta içi mevcut, Haftasonu tatil
    $dayOfWeek = date('N', strtotime($dateStr)); // 1=Pazartesi, 7=Pazar
    $isWeekend = ($dayOfWeek >= 6);
    
    return [
        'status' => $isWeekend ? 'holiday' : 'present',
        'start_time' => $isWeekend ? null : $defStart,
        'end_time' => $isWeekend ? null : $defEnd,
        'break_minutes' => $isWeekend ? 0 : $defBreak,
        'overtime_minutes' => 0,
        'unit_id' => null,
        'notes' => null,
        'source' => 'default'
    ];
}

/**
 * Bir çalışanın 4 haftalık şablonunu oluştur (yoksa)
 */
function ensureTemplate($userId) {
    global $pdo;
    $defStart = getSetting('work_start_time', '08:00');
    $defEnd = getSetting('work_end_time', '17:00');
    $defBreak = (int)getSetting('break_duration', '60');
    
    // Kullanıcının unit_id'sini al
    $stmt = $pdo->prepare("SELECT unit_id FROM users WHERE id=?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    $unitId = $user['unit_id'] ?? null;
    
    for ($i = 0; $i < 28; $i++) {
        $weekDay = ($i % 7); // 0=Pazartesi(döngü başı), ... 5=Cumartesi, 6=Pazar
        $isWeekend = ($weekDay >= 5);
        $status = $isWeekend ? 'holiday' : 'present';
        $start = $isWeekend ? null : $defStart;
        $end = $isWeekend ? null : $defEnd;
        $brk = $isWeekend ? 0 : $defBreak;
        
        $pdo->prepare("INSERT IGNORE INTO work_templates (user_id, day_index, unit_id, start_time, end_time, break_minutes, status) VALUES (?,?,?,?,?,?,?)")
            ->execute([$userId, $i, $unitId, $start, $end, $brk, $status]);
    }
}

/**
 * Toplu olarak bir ay için kayıtları ve şablonları cache'le
 */
function buildMonthCache($month, $year, $userIds) {
    global $pdo;
    
    $recordsCache = [];
    $templatesCache = [];
    
    // Manuel kayıtları çek
    if (!empty($userIds)) {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $params = array_merge($userIds, [$month, $year]);
        $stmt = $pdo->prepare("SELECT * FROM work_records WHERE user_id IN ($placeholders) AND MONTH(work_date)=? AND YEAR(work_date)=?");
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) {
            $recordsCache[$r['user_id']][$r['work_date']] = $r;
        }
    }
    
    // Şablonları çek
    if (!empty($userIds)) {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $pdo->prepare("SELECT * FROM work_templates WHERE user_id IN ($placeholders)");
        $stmt->execute($userIds);
        foreach ($stmt->fetchAll() as $t) {
            $templatesCache[$t['user_id']][$t['day_index']] = $t;
        }
    }
    
    return [$recordsCache, $templatesCache];
}

/**
 * Belirli bir tarih aralığı için kayıtları ve şablonları cache'le
 */
function buildRangeCache($startDate, $endDate, $userIds) {
    global $pdo;
    $recordsCache = [];
    $templatesCache = [];
    
    if (!empty($userIds)) {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        // Manuel kayıtlar
        $params = array_merge($userIds, [$startDate, $endDate]);
        $stmt = $pdo->prepare("SELECT * FROM work_records WHERE user_id IN ($placeholders) AND work_date >= ? AND work_date <= ?");
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) {
            $recordsCache[$r['user_id']][$r['work_date']] = $r;
        }
        
        // Şablonlar (Tüm şablonu çekmek en güvenlisi çünkü döngüsel)
        $stmt = $pdo->prepare("SELECT * FROM work_templates WHERE user_id IN ($placeholders)");
        $stmt->execute($userIds);
        foreach ($stmt->fetchAll() as $t) {
            $templatesCache[$t['user_id']][$t['day_index']] = $t;
        }
    }
    return [$recordsCache, $templatesCache];
}

// =====================================================
// İŞLEM YÖNLENDİRİCİ
// =====================================================
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// -- GİRİŞ --
if ($action === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username=? AND is_active=1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['username'] = $user['username'];
        setFlash('success', 'Hoş geldiniz, ' . $user['full_name'] . '!');
        redirect('dashboard');
    } else {
        setFlash('danger', 'Kullanıcı adı veya şifre hatalı!');
        redirect('login');
    }
}

// -- KAYIT --
if ($action === 'register') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $tc_no = trim($_POST['tc_no'] ?? '');
    $birth_date = $_POST['birth_date'] ?? null;
    $gender = $_POST['gender'] ?? null;
    $unit_id = $_POST['unit_id'] ?? null;
    $position = trim($_POST['position'] ?? '');

    if (!$username || !$password || !$full_name) { setFlash('danger', 'Zorunlu alanları doldurunuz!'); redirect('register'); }
    if ($password !== $password2) { setFlash('danger', 'Şifreler eşleşmiyor!'); redirect('register'); }
    if (strlen($password) < 6) { setFlash('danger', 'Şifre en az 6 karakter olmalı!'); redirect('register'); }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE username=?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) { setFlash('danger', 'Bu kullanıcı adı zaten kullanılıyor!'); redirect('register'); }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, address, tc_no, birth_date, gender, unit_id, position, role, hire_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,'employee',CURDATE())");
    $stmt->execute([$username, $hash, $full_name, $email ?: null, $phone ?: null, $address ?: null, $tc_no ?: null, $birth_date ?: null, $gender ?: null, $unit_id ?: null, $position ?: null]);
    
    // Yeni kayıt olan kullanıcı için şablon oluştur
    $newUserId = $pdo->lastInsertId();
    ensureTemplate($newUserId);
    
    setFlash('success', 'Kayıt başarılı! Giriş yapabilirsiniz.');
    redirect('login');
}

// -- ÇIKIŞ --
if ($action === 'logout') { session_destroy(); header("Location: ?page=login"); exit; }

// =====================================================
// ADMİN İŞLEMLERİ
// =====================================================
if (isLoggedIn() && isAdmin()) {

    // KURUM EKLE
    if ($action === 'add_institution') {
        $n = trim($_POST['name']??'');
        if ($n) { $pdo->prepare("INSERT INTO institutions (name,description,address,phone,email) VALUES (?,?,?,?,?)")->execute([$n, trim($_POST['description']??'') ?: null, trim($_POST['address']??'') ?: null, trim($_POST['phone']??'') ?: null, trim($_POST['email']??'') ?: null]); setFlash('success','Kurum eklendi.'); }
        redirect('institutions');
    }
    // KURUM GÜNCELLE
    if ($action === 'edit_institution') {
        $id=(int)($_POST['id']??0); $n=trim($_POST['name']??'');
        if ($id && $n) { $pdo->prepare("UPDATE institutions SET name=?,description=?,address=?,phone=?,email=?,is_active=? WHERE id=?")->execute([$n,trim($_POST['description']??'') ?: null,trim($_POST['address']??'') ?: null,trim($_POST['phone']??'') ?: null,trim($_POST['email']??'') ?: null,isset($_POST['is_active'])?1:0,$id]); setFlash('success','Kurum güncellendi.'); }
        redirect('institutions');
    }
    // KURUM SİL
    if ($action === 'delete_institution') { $id=(int)($_GET['id']??0); if ($id) { $pdo->prepare("DELETE FROM institutions WHERE id=?")->execute([$id]); setFlash('success','Kurum silindi.'); } redirect('institutions'); }

    // BİRİM EKLE
    if ($action === 'add_unit') {
        $iid=(int)($_POST['institution_id']??0); $n=trim($_POST['name']??'');
        if ($iid && $n) { $pdo->prepare("INSERT INTO units (institution_id,name,description,address,phone,manager_name) VALUES (?,?,?,?,?,?)")->execute([$iid,$n,trim($_POST['description']??'') ?: null,trim($_POST['address']??'') ?: null,trim($_POST['phone']??'') ?: null,trim($_POST['manager_name']??'') ?: null]); setFlash('success','Birim eklendi.'); }
        redirect('units');
    }
    // BİRİM GÜNCELLE
    if ($action === 'edit_unit') {
        $id=(int)($_POST['id']??0); $n=trim($_POST['name']??'');
        if ($id && $n) { $pdo->prepare("UPDATE units SET institution_id=?,name=?,description=?,address=?,phone=?,manager_name=?,is_active=? WHERE id=?")->execute([(int)($_POST['institution_id']??0),$n,trim($_POST['description']??'') ?: null,trim($_POST['address']??'') ?: null,trim($_POST['phone']??'') ?: null,trim($_POST['manager_name']??'') ?: null,isset($_POST['is_active'])?1:0,$id]); setFlash('success','Birim güncellendi.'); }
        redirect('units');
    }
    // BİRİM SİL
    if ($action === 'delete_unit') { $id=(int)($_GET['id']??0); if ($id) { $pdo->prepare("DELETE FROM units WHERE id=?")->execute([$id]); setFlash('success','Birim silindi.'); } redirect('units'); }

    // KULLANICI GÜNCELLE
    if ($action === 'admin_edit_user') {
        $id=(int)($_POST['id']??0);
        $un=trim($_POST['username']??'');
        if ($id && $un) {
            // Kullanıcı adı çakışma kontrolü
            $chk=$pdo->prepare("SELECT id FROM users WHERE username=? AND id!=?");
            $chk->execute([$un,$id]);
            if ($chk->fetch()) {
                setFlash('danger','Bu kullanıcı adı başka bir kullanıcı tarafından kullanılıyor!');
            } else {
                $sql = "UPDATE users SET full_name=?,username=?,email=?,phone=?,address=?,tc_no=?,birth_date=?,gender=?,unit_id=?,position=?,role=?,is_active=?";
                $p = [trim($_POST['full_name']??''),$un,trim($_POST['email']??'') ?: null,trim($_POST['phone']??'') ?: null,trim($_POST['address']??'') ?: null,trim($_POST['tc_no']??'') ?: null,$_POST['birth_date'] ?: null,$_POST['gender'] ?: null,$_POST['unit_id'] ?: null,trim($_POST['position']??'') ?: null,$_POST['role']??'employee',isset($_POST['is_active'])?1:0];
                if ($_POST['new_password']??'') { $sql.=",password=?"; $p[]=password_hash($_POST['new_password'],PASSWORD_DEFAULT); }
                $sql.=" WHERE id=?"; $p[]=$id;
                $pdo->prepare($sql)->execute($p);
                ensureTemplate($id);
                setFlash('success','Kullanıcı güncellendi.');
            }
        }
        redirect('users');
    }
    // KULLANICI SİL
    if ($action === 'delete_user') { $id=(int)($_GET['id']??0); if ($id && $id != (int)currentUserId()) { $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]); setFlash('success','Kullanıcı silindi.'); } redirect('users'); }
    // KULLANICI EKLE
    if ($action === 'admin_add_user') {
        $un=trim($_POST['username']??''); $pw=$_POST['password']??''; $fn=trim($_POST['full_name']??'');
        if ($un && $pw && $fn) {
            $chk=$pdo->prepare("SELECT id FROM users WHERE username=?"); $chk->execute([$un]);
            if ($chk->fetch()) { setFlash('danger','Bu kullanıcı adı mevcut!'); }
            else {
                $pdo->prepare("INSERT INTO users (username,password,full_name,email,phone,role,unit_id,position,hire_date) VALUES (?,?,?,?,?,?,?,?,CURDATE())")
                    ->execute([$un,password_hash($pw,PASSWORD_DEFAULT),$fn,trim($_POST['email']??'') ?: null,trim($_POST['phone']??'') ?: null,$_POST['role']??'employee',$_POST['unit_id'] ?: null,trim($_POST['position']??'') ?: null]);
                $newId = $pdo->lastInsertId();
                ensureTemplate($newId);
                setFlash('success','Kullanıcı eklendi ve şablonu oluşturuldu.');
            }
        }
        redirect('users');
    }

    // PUANTAJ TEK GÜN GİRİŞİ (override)
    if ($action === 'add_work_record') {
        $uid=(int)($_POST['user_id']??0); $dt=$_POST['work_date']??'';
        if ($uid && $dt) {
            $st=$_POST['start_time'] ?: null; $et=$_POST['end_time'] ?: null;
            $brk=(int)($_POST['break_minutes']??0); $ot=(int)($_POST['overtime_minutes']??0);
            $status=$_POST['status']??'present'; $notes=trim($_POST['notes']??''); $unitId=(int)($_POST['unit_id']??0);
            
            $chk=$pdo->prepare("SELECT id FROM work_records WHERE user_id=? AND work_date=?"); $chk->execute([$uid,$dt]);
            if ($chk->fetch()) {
                $pdo->prepare("UPDATE work_records SET unit_id=?,start_time=?,end_time=?,break_minutes=?,overtime_minutes=?,status=?,notes=?,created_by=? WHERE user_id=? AND work_date=?")
                    ->execute([$unitId ?: null,$st,$et,$brk,$ot,$status,$notes ?: null,currentUserId(),$uid,$dt]);
            } else {
                $pdo->prepare("INSERT INTO work_records (user_id,unit_id,work_date,start_time,end_time,break_minutes,overtime_minutes,status,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$uid,$unitId ?: null,$dt,$st,$et,$brk,$ot,$status,$notes ?: null,currentUserId()]);
            }
            setFlash('success','Puantaj kaydı güncellendi.');
        }
        $m = $_POST['month'] ?? date('n', strtotime($_POST['work_date'] ?? 'now'));
        $y = $_POST['year'] ?? date('Y', strtotime($_POST['work_date'] ?? 'now'));
        $uf = (int)($_POST['unit_filter'] ?? 0);
        $usf = (int)($_POST['user_filter'] ?? 0);
        redirect("timesheet&month=$m&year=$y" . ($uf ? "&unit_filter=$uf" : "") . ($usf ? "&user_filter=$usf" : ""));
    }

    // PUANTAJ OVERRIDE SİL (şablona geri dön)
    if ($action === 'reset_work_record') {
        $uid=(int)($_GET['uid']??0); $dt=$_GET['date']??'';
        if ($uid && $dt) {
            $pdo->prepare("DELETE FROM work_records WHERE user_id=? AND work_date=?")->execute([$uid,$dt]);
            setFlash('success','Kayıt sıfırlandı, şablon değeri kullanılacak.');
        }
        $m = $_GET['month'] ?? date('n', strtotime($dt ?: 'now'));
        $y = $_GET['year'] ?? date('Y', strtotime($dt ?: 'now'));
        $uf = (int)($_GET['unit_filter'] ?? 0);
        $usf = (int)($_GET['user_filter'] ?? 0);
        redirect("timesheet&month=$m&year=$y" . ($uf ? "&unit_filter=$uf" : "") . ($usf ? "&user_filter=$usf" : ""));
    }

    // TOPLU PUANTAJ GİRİŞİ
    if ($action === 'bulk_work_record') {
        $dt=$_POST['work_date']??''; $udata=$_POST['users']??[];
        if ($dt && !empty($udata)) {
            foreach ($udata as $uid => $d) {
                $uid=(int)$uid; $status=$d['status']??'present';
                $st=$d['start_time'] ?: null; $et=$d['end_time'] ?: null;
                $brk=(int)($d['break_minutes']??0); $ot=(int)($d['overtime_minutes']??0);
                $unitId=(int)($d['unit_id']??0); $notes=trim($d['notes']??'');
                
                // Sadece varsayılandan farklıysa kaydet
                $default = getDayStatus($uid, $dt);
                $isDifferent = ($status !== ($default['status']??'present')) ||
                               ($st && $st !== ($default['start_time']??'')) ||
                               ($et && $et !== ($default['end_time']??'')) ||
                               ($brk != ($default['break_minutes']??0)) ||
                               ($ot > 0) || ($notes !== '') || ($unitId > 0 && $unitId != ($default['unit_id']??0));
                
                if ($isDifferent) {
                    $chk=$pdo->prepare("SELECT id FROM work_records WHERE user_id=? AND work_date=?"); $chk->execute([$uid,$dt]);
                    if ($chk->fetch()) {
                        $pdo->prepare("UPDATE work_records SET unit_id=?,start_time=?,end_time=?,break_minutes=?,overtime_minutes=?,status=?,notes=?,created_by=? WHERE user_id=? AND work_date=?")
                            ->execute([$unitId ?: null,$st,$et,$brk,$ot,$status,$notes ?: null,currentUserId(),$uid,$dt]);
                    } else {
                        $pdo->prepare("INSERT INTO work_records (user_id,unit_id,work_date,start_time,end_time,break_minutes,overtime_minutes,status,notes,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
                            ->execute([$uid,$unitId ?: null,$dt,$st,$et,$brk,$ot,$status,$notes ?: null,currentUserId()]);
                    }
                }
            }
            setFlash('success','Toplu puantaj güncellendi.');
        }
        $m = $_POST['month'] ?? date('n', strtotime($_POST['work_date'] ?? 'now'));
        $y = $_POST['year'] ?? date('Y', strtotime($_POST['work_date'] ?? 'now'));
        $uf = (int)($_POST['unit_filter'] ?? 0);
        $usf = (int)($_POST['user_filter'] ?? 0);
        redirect("timesheet&month=$m&year=$y" . ($uf ? "&unit_filter=$uf" : "") . ($usf ? "&user_filter=$usf" : ""));
    }

    // ŞABLON GÜNCELLE
    if ($action === 'update_template') {
        $uid=(int)($_POST['user_id']??0);
        if ($uid) {
            $tdata = $_POST['template'] ?? [];
            foreach ($tdata as $dayIdx => $d) {
                $dayIdx = (int)$dayIdx;
                $status = $d['status'] ?? 'present';
                $st = $d['start_time'] ?: null;
                $et = $d['end_time'] ?: null;
                $brk = (int)($d['break_minutes'] ?? 0);
                $unitId = (int)($d['unit_id'] ?? 0);
                $notes = trim($d['notes'] ?? '');
                
                $pdo->prepare("INSERT INTO work_templates (user_id,day_index,unit_id,start_time,end_time,break_minutes,status,notes) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE unit_id=VALUES(unit_id),start_time=VALUES(start_time),end_time=VALUES(end_time),break_minutes=VALUES(break_minutes),status=VALUES(status),notes=VALUES(notes)")
                    ->execute([$uid,$dayIdx,$unitId ?: null,$st,$et,$brk,$status,$notes ?: null]);
            }
            setFlash('success','4 haftalık şablon güncellendi.');
        }
        redirect('template&uid=' . $uid);
    }

    // DÖNGÜ AYARI
    if ($action === 'update_cycle') {
        $startDate = $_POST['cycle_start_date'] ?? '';
        if ($startDate) {
            $pdo->prepare("UPDATE cycle_config SET cycle_start_date=? WHERE id=1")->execute([$startDate]);
            setFlash('success','Döngü başlangıç tarihi güncellendi.');
        }
        redirect('settings');
    }

    // ŞABLON SIFIRLA (TEK KULLANICI)
    if ($action === 'reset_user_template') {
        $uid = (int)($_GET['uid']??0);
        if ($uid) {
            $pdo->prepare("DELETE FROM work_templates WHERE user_id=?")->execute([$uid]);
            ensureTemplate($uid);
            setFlash('success','Şablon varsayılanlara döndürüldü.');
        }
        redirect('templates');
    }

    // İZİN ONAYLA/REDDET
    if ($action === 'approve_leave') {
        $id=(int)($_POST['id']??0); $status=$_POST['status']??'';
        if ($id && in_array($status,['approved','rejected'])) {
            $pdo->prepare("UPDATE leave_records SET status=?,approved_by=?,approved_at=NOW(),rejection_reason=? WHERE id=?")
                ->execute([$status,currentUserId(),trim($_POST['rejection_reason']??'') ?: null,$id]);
            setFlash('success','İzin durumu güncellendi.');
        }
        redirect('leaves');
    }
    if ($action === 'delete_leave') { $id=(int)($_GET['id']??0); if ($id) { $pdo->prepare("DELETE FROM leave_records WHERE id=?")->execute([$id]); setFlash('success','İzin silindi.'); } redirect('leaves'); }
    if ($action === 'admin_add_leave') {
        $uid=(int)($_POST['user_id']??0); $sd=$_POST['start_date']??''; $ed=$_POST['end_date']??'';
        if ($uid && $sd && $ed) {
            $days=max(1,(strtotime($ed)-strtotime($sd))/86400+1);
            $pdo->prepare("INSERT INTO leave_records (user_id,leave_type,start_date,end_date,total_days,reason,status,approved_by,approved_at) VALUES (?,?,?,?,?,?,?,?,NOW())")
                ->execute([$uid,$_POST['leave_type']??'annual',$sd,$ed,$days,trim($_POST['reason']??'') ?: null,$_POST['status']??'approved',currentUserId()]);
            setFlash('success','İzin eklendi.');
        }
        redirect('leaves');
    }

    // AYARLAR
    if ($action === 'update_settings') {
        foreach ($_POST['settings'] as $k => $v) { $pdo->prepare("UPDATE settings SET setting_value=? WHERE setting_key=?")->execute([trim($v),$k]); }
        setFlash('success','Ayarlar güncellendi.');
        redirect('settings');
    }

    // WORD EXPORT
    if ($action === 'export_word') {
        $selMonth = (int)($_GET['month'] ?? date('n'));
        $selYear = (int)($_GET['year'] ?? date('Y'));
        $selUnit = (int)($_GET['unit_filter'] ?? 0);
        $selUser = (int)($_GET['user_filter'] ?? 0);
        $startDate = $_GET['start_date'] ?? '';
        $endDate = $_GET['end_date'] ?? '';
        
        if ($startDate && $endDate) {
            $repStart = $startDate;
            $repEnd = $endDate;
            $title = formatDate($repStart) . " - " . formatDate($repEnd) . " Raporu";
        } else {
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selMonth, $selYear);
            $repStart = sprintf('%04d-%02d-01', $selYear, $selMonth);
            $repEnd = sprintf('%04d-%02d-%02d', $selYear, $selMonth, $daysInMonth);
            $title = turkishMonth($selMonth) . " $selYear Raporu";
        }
        
        $userWhere = "u.is_active=1";
        $params = [];
        if ($selUnit) { $userWhere .= " AND u.unit_id=?"; $params[] = $selUnit; }
        if ($selUser) { $userWhere .= " AND u.id=?"; $params[] = $selUser; }
        $stmt = $pdo->prepare("SELECT u.id, u.full_name, un.name as unit_name FROM users u LEFT JOIN units un ON u.unit_id=un.id WHERE $userWhere ORDER BY u.full_name");
        $stmt->execute($params);
        $employees = $stmt->fetchAll();
        $empIds = array_column($employees, 'id');
        list($recCache, $tplCache) = buildRangeCache($repStart, $repEnd, $empIds);

        header("Content-Type: application/vnd.ms-word; charset=utf-8");
        header("Content-Disposition: attachment; filename=puantaj-rapor.doc");
        header("Pragma: no-cache");
        header("Expires: 0");
        
        echo "<html><head><meta charset='utf-8'></head><body>";
        echo "<h2 style='text-align:center;'>".sanitize($companyName)."</h2>";
        echo "<h3 style='text-align:center;'>".sanitize($title)."</h3>";
        if($selUnit) {
            $un = $pdo->prepare("SELECT name FROM units WHERE id=?"); $un->execute([$selUnit]);
            echo "<p><b>Birim:</b> ".sanitize($un->fetchColumn())."</p>";
        }
        echo "<table border='1' cellspacing='0' cellpadding='5' width='100%'>";
        echo "<thead><tr style='background:#f2f2f2;'><th>Çalışan</th><th>Birim</th><th>Mevcut</th><th>Devamsız</th><th>İzinli</th><th>Hasta</th><th>Tatil</th><th>½ Gün</th></tr></thead>";
        echo "<tbody>";
        foreach($employees as $emp) {
            $counts = ['present'=>0,'absent'=>0,'leave'=>0,'sick'=>0,'holiday'=>0,'half_day'=>0];
            $cur = new DateTime($repStart);
            $last = new DateTime($repEnd);
            while($cur <= $last) {
                $ds = $cur->format('Y-m-d');
                $hasOvr = isset($recCache[$emp['id']][$ds]);
                $dayData = $hasOvr ? $recCache[$emp['id']][$ds] : getDayStatus($emp['id'], $ds, $recCache, $tplCache);
                $st = $dayData['status'] ?? 'present';
                if (isset($counts[$st])) $counts[$st]++;
                $cur->modify('+1 day');
            }
            echo "<tr><td>".sanitize($emp['full_name'])."</td><td>".sanitize($emp['unit_name']??'-')."</td><td align='center'>{$counts['present']}</td><td align='center'>{$counts['absent']}</td><td align='center'>{$counts['leave']}</td><td align='center'>{$counts['sick']}</td><td align='center'>{$counts['holiday']}</td><td align='center'>{$counts['half_day']}</td></tr>";
        }
        echo "</tbody></table>";
        echo "<p style='margin-top:20px; text-align:right;'><b>Rapor Tarihi:</b> ".date('d.m.Y H:i')."</p>";
        echo "</body></html>";
        exit;
    }

    // TATİL
    if ($action === 'add_holiday') {
        $n=trim($_POST['name']??''); $d=$_POST['date']??'';
        if ($n && $d) { $pdo->prepare("INSERT INTO holidays (name,date,is_recurring) VALUES (?,?,?) ON DUPLICATE KEY UPDATE name=?,is_recurring=?")->execute([$n,$d,isset($_POST['is_recurring'])?1:0,$n,isset($_POST['is_recurring'])?1:0]); setFlash('success','Tatil eklendi.'); }
        redirect('holidays');
    }
    if ($action === 'delete_holiday') { $id=(int)($_GET['id']??0); if ($id) { $pdo->prepare("DELETE FROM holidays WHERE id=?")->execute([$id]); setFlash('success','Tatil silindi.'); } redirect('holidays'); }

    // TÜM ŞABLONLARI OLUŞTUR
    if ($action === 'generate_all_templates') {
        $allUsers = $pdo->query("SELECT id FROM users WHERE is_active=1 AND role='employee'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($allUsers as $uid) { ensureTemplate($uid); }
        setFlash('success', count($allUsers) . ' çalışan için şablonlar oluşturuldu/kontrol edildi.');
        redirect('settings');
    }

    // TARİH ARALIĞINDA ŞABLONDAN PUANTAJ OLUŞTUR
    if ($action === 'generate_range_records') {
        $start = $_POST['start_date'] ?? '';
        $end = $_POST['end_date'] ?? '';
        $cleanup = isset($_POST['cleanup_others']);
        
        if ($start && $end) {
            $pdo->beginTransaction();
            try {
                if ($cleanup) {
                    $pdo->prepare("DELETE FROM work_records WHERE work_date < ? OR work_date > ?")->execute([$start, $end]);
                }
                
                $emps = $pdo->query("SELECT id, unit_id FROM users WHERE role='employee' AND is_active=1")->fetchAll();
                $cur = new DateTime($start);
                $last = new DateTime($end);
                
                $allTpl = $pdo->query("SELECT * FROM work_templates")->fetchAll();
                $tplMap = [];
                foreach($allTpl as $t) { $tplMap[$t['user_id']][$t['day_index']] = $t; }

                $stmt = $pdo->prepare("INSERT INTO work_records (user_id, unit_id, work_date, start_time, end_time, break_minutes, status, notes, created_by) 
                                       VALUES (?,?,?,?,?,?,?,?,?)
                                       ON DUPLICATE KEY UPDATE unit_id=VALUES(unit_id), start_time=VALUES(start_time), end_time=VALUES(end_time), 
                                       break_minutes=VALUES(break_minutes), status=VALUES(status), notes=VALUES(notes), created_by=VALUES(created_by)");

                while ($cur <= $last) {
                    $dateStr = $cur->format('Y-m-d');
                    $dayIdx = getCycleDayIndex($dateStr);
                    foreach ($emps as $e) {
                        $tpl = $tplMap[$e['id']][$dayIdx] ?? null;
                        if (!$tpl) {
                            $dayOfWeek = (int)$cur->format('N');
                            $isWeekend = ($dayOfWeek >= 6);
                            $status = $isWeekend ? 'holiday' : 'present';
                            $st = $isWeekend ? null : getSetting('work_start_time', '08:00');
                            $et = $isWeekend ? null : getSetting('work_end_time', '17:00');
                            $brk = $isWeekend ? 0 : (int)getSetting('break_duration', '60');
                            $unitId = $e['unit_id'];
                            $notes = null;
                        } else {
                            $status = $tpl['status'];
                            $st = $tpl['start_time'];
                            $et = $tpl['end_time'];
                            $brk = $tpl['break_minutes'];
                            $unitId = $tpl['unit_id'] ?: $e['unit_id'];
                            $notes = $tpl['notes'];
                        }
                        $stmt->execute([$e['id'], $unitId, $dateStr, $st, $et, $brk, $status, $notes, currentUserId()]);
                    }
                    $cur->modify('+1 day');
                }
                $pdo->commit();
                setFlash('success', 'Belirtilen tarih aralığı için puantaj kayıtları şablondan oluşturuldu.');
            } catch (Exception $e) {
                $pdo->rollBack();
                setFlash('danger', 'Hata oluştu: ' . $e->getMessage());
            }
        }
        redirect('settings');
    }

    // PUANTAJ TEMİZLE (Sıfırla)
    if ($action === 'delete_work_records') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $start = $_POST['start_date'] ?? '';
        $end = $_POST['end_date'] ?? '';
        $deleteLeaves = isset($_POST['delete_leaves']);
        $resetTemplates = isset($_POST['reset_templates']);
        
        $pdo->beginTransaction();
        try {
            // 1. Manuel kayıtları sil (work_records)
            $sql = "DELETE FROM work_records WHERE 1=1";
            $params = [];
            if ($uid > 0) { $sql .= " AND user_id = ?"; $params[] = $uid; }
            if ($start) { $sql .= " AND work_date >= ?"; $params[] = $start; }
            if ($end) { $sql .= " AND work_date <= ?"; $params[] = $end; }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $count = $stmt->rowCount();

            // 2. Onaylı izinleri sil (leave_records)
            if ($deleteLeaves) {
                $lSql = "DELETE FROM leave_records WHERE 1=1";
                $lParams = [];
                if ($uid > 0) { $lSql .= " AND user_id = ?"; $lParams[] = $uid; }
                if ($start) { $lSql .= " AND start_date >= ?"; $lParams[] = $start; }
                if ($end) { $lSql .= " AND end_date <= ?"; $lParams[] = $end; }
                $pdo->prepare($lSql)->execute($lParams);
            }

            // 3. Şablonu sıfırla (work_templates)
            if ($resetTemplates) {
                $indices = [];
                if ($start && $end) {
                    $c = new DateTime($start);
                    $l = new DateTime($end);
                    $diffDays = (int)$c->diff($l)->format('%a');
                    if ($diffDays >= 27) {
                        for($i=0;$i<28;$i++) $indices[] = $i;
                    } else {
                        $tmp = clone $c;
                        while($tmp <= $l) {
                            $indices[] = getCycleDayIndex($tmp->format('Y-m-d'));
                            $tmp->modify('+1 day');
                        }
                        $indices = array_unique($indices);
                    }
                } else {
                    for($i=0;$i<28;$i++) $indices[] = $i;
                }

                if (!empty($indices)) {
                    $idxList = implode(',', $indices);
                    $defStart = getSetting('work_start_time','08:00');
                    $defEnd = getSetting('work_end_time','17:00');
                    $defBrk = (int)getSetting('break_duration', 60);
                    
                    $tplSql = "UPDATE work_templates SET 
                               status = CASE WHEN (day_index % 7) >= 5 THEN 'holiday' ELSE 'present' END,
                               start_time = CASE WHEN (day_index % 7) >= 5 THEN NULL ELSE ? END,
                               end_time = CASE WHEN (day_index % 7) >= 5 THEN NULL ELSE ? END,
                               break_minutes = CASE WHEN (day_index % 7) >= 5 THEN 0 ELSE ? END,
                               notes = NULL
                               WHERE day_index IN ($idxList)";
                    $tplParams = [$defStart, $defEnd, $defBrk];
                    if ($uid > 0) {
                        $tplSql .= " AND user_id = ?";
                        $tplParams[] = $uid;
                    }
                    $pdo->prepare($tplSql)->execute($tplParams);
                }
            }

            $pdo->commit();
            setFlash('success', "$count adet manuel puantaj kaydı silindi. Seçilen ek temizleme işlemleri uygulandı.");
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('danger', 'Hata oluştu: ' . $e->getMessage());
        }
        redirect('settings');
    }
}

// ÇALIŞAN: PROFİL
if (isLoggedIn() && $action === 'update_profile') {
    $id=currentUserId(); $fn=trim($_POST['full_name']??'');
    if ($fn) {
        $sql="UPDATE users SET full_name=?,email=?,phone=?,address=?";
        $p=[$fn,trim($_POST['email']??'') ?: null,trim($_POST['phone']??'') ?: null,trim($_POST['address']??'') ?: null];
        if ($_POST['new_password']??'') {
            $chk=$pdo->prepare("SELECT password FROM users WHERE id=?"); $chk->execute([$id]); $u=$chk->fetch();
            if (!password_verify($_POST['current_password']??'',$u['password'])) { setFlash('danger','Mevcut şifre hatalı!'); redirect('profile'); }
            $sql.=",password=?"; $p[]=password_hash($_POST['new_password'],PASSWORD_DEFAULT);
        }
        $sql.=" WHERE id=?"; $p[]=$id;
        $pdo->prepare($sql)->execute($p);
        $_SESSION['user_name']=$fn;
        setFlash('success','Profil güncellendi.');
    }
    redirect('profile');
}

// ÇALIŞAN: İZİN TALEBİ
if (isLoggedIn() && $action === 'request_leave') {
    $uid=currentUserId(); $sd=$_POST['start_date']??''; $ed=$_POST['end_date']??'';
    if ($sd && $ed) {
        $days=max(1,(strtotime($ed)-strtotime($sd))/86400+1);
        $pdo->prepare("INSERT INTO leave_records (user_id,leave_type,start_date,end_date,total_days,reason,status) VALUES (?,?,?,?,?,?,?)")
            ->execute([$uid,$_POST['leave_type']??'annual',$sd,$ed,$days,trim($_POST['reason']??'') ?: null,'pending']);
        setFlash('success','İzin talebiniz gönderildi.');
    }
    redirect('my_leaves');
}

// =====================================================
// SAYFA YÖNLENDİRME
// =====================================================
$page = $_GET['page'] ?? '';
if (!isLoggedIn() && !in_array($page, ['login','register',''])) $page = 'login';
if ($page === '' || $page === 'home') $page = isLoggedIn() ? 'dashboard' : 'login';

$pageTitle = getSetting('site_title', 'Puantaj Sistemi');
$themeColor = getSetting('theme_color', '#2563eb');
$companyName = getSetting('company_name', 'Çeşme Belediyesi');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: <?=$themeColor?>; --primary-dark: <?=$themeColor?>dd; --sidebar-width: 270px; }
        * { font-family: 'Inter', sans-serif; }
        body { background: #f0f2f5; min-height: 100vh; }
        .sidebar { position:fixed; top:0; bottom:0; left:0; width:var(--sidebar-width); background:linear-gradient(180deg,#1e293b,#0f172a); color:#fff; z-index:1040; transition:transform .3s; overflow-y:auto; padding-bottom:3rem; }
        .sidebar .brand { padding:1.25rem; border-bottom:1px solid rgba(255,255,255,.1); text-align:center; }
        .sidebar .brand h4 { margin:0; font-weight:700; font-size:1rem; }
        .sidebar .brand small { color:rgba(255,255,255,.5); font-size:.7rem; }
        .sidebar-nav { padding:.75rem 0; }
        .sidebar-nav .nav-label { padding:.4rem 1.25rem; font-size:.65rem; text-transform:uppercase; letter-spacing:1px; color:rgba(255,255,255,.35); font-weight:600; }
        .sidebar-nav a { display:flex; align-items:center; padding:.55rem 1.25rem; color:rgba(255,255,255,.65); text-decoration:none; font-size:.85rem; transition:all .2s; border-left:3px solid transparent; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background:rgba(255,255,255,.08); color:#fff; border-left-color:var(--primary); }
        .sidebar-nav a i { width:22px; margin-right:10px; font-size:1rem; }
        .main-content { margin-left:var(--sidebar-width); transition:margin-left .3s; }
        .top-nav { background:#fff; padding:.6rem 1.25rem; box-shadow:0 1px 3px rgba(0,0,0,.08); display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:1030; }
        .top-nav .btn-sidebar-toggle { display:none; border:none; background:none; font-size:1.3rem; color:#333; cursor:pointer; }
        .page-content { padding:1.25rem; }
        .stat-card { background:#fff; border-radius:12px; padding:1.25rem; box-shadow:0 1px 3px rgba(0,0,0,.08); transition:transform .2s; }
        .stat-card:hover { transform:translateY(-2px); }
        .stat-card .stat-icon { width:44px; height:44px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; color:#fff; }
        .stat-card .stat-value { font-size:1.6rem; font-weight:700; color:#1e293b; }
        .stat-card .stat-label { font-size:.8rem; color:#64748b; }
        .table-card { background:#fff; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,.08); overflow:hidden; }
        .table-card .card-header { background:#fff; border-bottom:1px solid #e2e8f0; padding:.85rem 1.25rem; }
        .table th { background:#f8fafc; font-weight:600; font-size:.75rem; text-transform:uppercase; letter-spacing:.5px; color:#64748b; }
        .table td { vertical-align:middle; font-size:.85rem; }
        .badge { font-weight:500; padding:.3rem .55rem; font-size:.72rem; }
        .auth-page { min-height:100vh; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg,#667eea,#764ba2); padding:1rem; }
        .auth-card { background:#fff; border-radius:16px; padding:2rem; width:100%; max-width:480px; box-shadow:0 25px 50px rgba(0,0,0,.15); }
        .auth-card h2 { font-weight:700; color:#1e293b; }
        .sidebar-overlay { display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.5); z-index:1035; }
        .form-label { font-weight:500; font-size:.82rem; color:#374151; }
        .form-control,.form-select { border-radius:8px; border:1px solid #d1d5db; padding:.5rem .75rem; font-size:.85rem; }
        .form-control:focus,.form-select:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(37,99,235,.1); }
        .btn-primary { background:var(--primary); border-color:var(--primary); border-radius:8px; font-weight:500; }
        .btn-primary:hover { background:var(--primary-dark); border-color:var(--primary-dark); }
        .user-avatar { width:34px; height:34px; border-radius:50%; background:var(--primary); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:600; font-size:.8rem; }
        .timesheet-scroll { overflow-x:auto; -webkit-overflow-scrolling:touch; }
        @media (max-width:991.98px) {
            .sidebar { transform:translateX(-100%); }
            .sidebar.show { transform:translateX(0); }
            .sidebar-overlay.show { display:block; }
            .main-content { margin-left:0; }
            .top-nav .btn-sidebar-toggle { display:block; }
        }
        @media print { .sidebar,.top-nav,.no-print { display:none!important; } .main-content { margin-left:0!important; } }
        .sidebar::-webkit-scrollbar { width:4px; }
        .sidebar::-webkit-scrollbar-thumb { background:rgba(255,255,255,.2); border-radius:2px; }

        /* Puantaj hücreleri */
        .ts-cell { width:36px; height:36px; display:flex; align-items:center; justify-content:center; border-radius:6px; font-size:.7rem; font-weight:600; cursor:pointer; margin:auto; transition:all .15s; }
        .ts-cell:hover { transform:scale(1.15); box-shadow:0 2px 8px rgba(0,0,0,.15); }
        .ts-present { background:#dcfce7; color:#166534; }
        .ts-absent { background:#fecaca; color:#991b1b; }
        .ts-leave { background:#f97316; color:#fff; }
        .ts-sick { background:#fef3c7; color:#92400e; }
        .ts-holiday { background:#e2e8f0; color:#475569; }
        .ts-half_day { background:#dbeafe; color:#1e40af; }
        .ts-override { border:2px solid #f97316; }

        /* Custom Orange Utilities */
        .bg-orange { background-color: #f97316 !important; color: #fff !important; }
        .bg-orange.bg-opacity-25 { background-color: rgba(249, 115, 22, 0.25) !important; color: inherit !important; }

        /* Mobile Timesheet Styles */
        .mobile-ts-card { background:#fff; border-radius:12px; margin-bottom:1rem; box-shadow:0 1px 3px rgba(0,0,0,.08); overflow:hidden; border:1px solid #e2e8f0; }
        .mobile-ts-card .card-header { background:#f8fafc; padding:.75rem 1rem; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; }
        .mobile-ts-card .card-body { padding:1rem; }
        .mobile-ts-week { margin-bottom:1rem; }
        .mobile-ts-week:last-child { margin-bottom:0; }
        .mobile-ts-week-label { font-size:.65rem; text-transform:uppercase; color:#64748b; font-weight:700; margin-bottom:.5rem; display:block; border-bottom:1px solid #f1f5f9; padding-bottom:2px; letter-spacing:0.5px; }
        .mobile-ts-grid { display:grid; grid-template-columns:repeat(7, 1fr); gap:6px; }
        .ts-cell-mobile { aspect-ratio:1/1; display:flex; flex-direction:column; align-items:center; justify-content:center; border-radius:8px; font-weight:700; cursor:pointer; position:relative; transition:transform 0.1s; border:1px solid rgba(0,0,0,0.05); }
        .ts-cell-mobile:active { transform:scale(0.95); }
        .ts-cell-mobile .day-num { font-size:.5rem; position:absolute; top:2px; left:4px; opacity:.5; }
        .ts-cell-mobile .symbol { font-size:.85rem; margin-top:4px; }
        .mobile-ts-grid-header { display:grid; grid-template-columns:repeat(7, 1fr); gap:6px; margin-bottom:5px; text-align:center; }
        .mobile-ts-day-label { font-size:.65rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; }


        /* Template grid */
        .tpl-day { border:1px solid #e5e7eb; border-radius:8px; padding:.5rem; background:#fff; transition:all .2s; }
        .tpl-day:hover { box-shadow:0 2px 8px rgba(0,0,0,.1); }
        .tpl-day.weekend { background:#fef2f2; }
    </style>
</head>
<body>

<?php if ($page === 'login'): ?>
<div class="auth-page">
    <div class="auth-card">
        <div class="text-center mb-4">
            <i class="bi bi-clock-history" style="font-size:2.5rem;color:var(--primary)"></i>
            <h2 class="mt-2"><?= sanitize($pageTitle) ?></h2>
            <p class="text-muted mb-0"><?= sanitize($companyName) ?></p>
        </div>
        <?php $fl=getFlash(); if($fl): ?><div class="alert alert-<?=$fl['type']?> alert-dismissible fade show"><small><?=$fl['message']?></small><button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="action" value="login">
            <div class="mb-3"><label class="form-label">Kullanıcı Adı</label><input type="text" name="username" class="form-control" required autofocus></div>
            <div class="mb-3"><label class="form-label">Şifre</label><input type="password" name="password" class="form-control" required></div>
            <button type="submit" class="btn btn-primary btn-lg w-100 mb-3"><i class="bi bi-box-arrow-in-right me-2"></i>Giriş Yap</button>
        </form>
        <div class="text-center"><a href="?page=register" class="text-decoration-none small">Hesabınız yok mu? <strong>Kayıt Olun</strong></a></div>
    </div>
</div>

<?php elseif ($page === 'register'):
    $units = $pdo->query("SELECT u.*, i.name as inst_name FROM units u LEFT JOIN institutions i ON u.institution_id=i.id WHERE u.is_active=1 ORDER BY i.name,u.name")->fetchAll();
?>
<div class="auth-page">
    <div class="auth-card" style="max-width:540px">
        <div class="text-center mb-3">
            <i class="bi bi-person-plus" style="font-size:2.5rem;color:var(--primary)"></i>
            <h2 class="mt-2">Kayıt Ol</h2>
        </div>
        <?php $fl=getFlash(); if($fl): ?><div class="alert alert-<?=$fl['type']?> alert-dismissible fade show"><small><?=$fl['message']?></small><button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="action" value="register">
            <div class="row g-2">
                <div class="col-md-6"><label class="form-label">Ad Soyad *</label><input type="text" name="full_name" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Kullanıcı Adı *</label><input type="text" name="username" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Şifre *</label><input type="password" name="password" class="form-control" required minlength="6"></div>
                <div class="col-md-6"><label class="form-label">Şifre Tekrar *</label><input type="password" name="password2" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">E-posta</label><input type="email" name="email" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Telefon</label><input type="tel" name="phone" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">TC No</label><input type="text" name="tc_no" class="form-control" maxlength="11"></div>
                <div class="col-md-6"><label class="form-label">Doğum Tarihi</label><input type="date" name="birth_date" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Cinsiyet</label><select name="gender" class="form-select"><option value="">Seçiniz</option><option>Erkek</option><option>Kadın</option><option>Diğer</option></select></div>
                <div class="col-md-6"><label class="form-label">Birim</label><select name="unit_id" class="form-select"><option value="">Seçiniz</option><?php foreach($units as $u): ?><option value="<?=$u['id']?>"><?=sanitize($u['inst_name'].' - '.$u['name'])?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label class="form-label">Pozisyon</label><input type="text" name="position" class="form-control"></div>
                <div class="col-12"><label class="form-label">Adres</label><textarea name="address" class="form-control" rows="2"></textarea></div>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100 mt-3"><i class="bi bi-check-circle me-2"></i>Kayıt Ol</button>
        </form>
        <div class="text-center mt-2"><a href="?page=login" class="text-decoration-none small">Zaten hesabınız var mı? <strong>Giriş Yapın</strong></a></div>
    </div>
</div>

<?php else: ?>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
<nav class="sidebar" id="sidebar">
    <div class="brand">
        <h4><i class="bi bi-clock-history me-2"></i><?=sanitize($pageTitle)?></h4>
        <small><?=sanitize($companyName)?></small>
    </div>
    <div class="sidebar-nav">
        <div class="nav-label">Ana Menü</div>
        <a href="?page=dashboard" class="<?=$page==='dashboard'?'active':''?>"><i class="bi bi-speedometer2"></i>Kontrol Paneli</a>
        <?php if (isAdmin()): ?>
            <div class="nav-label mt-2">Yönetim</div>
            <a href="?page=users" class="<?=$page==='users'||$page==='edit_user'?'active':''?>"><i class="bi bi-people"></i>Çalışanlar</a>
            <a href="?page=institutions" class="<?=$page==='institutions'||$page==='edit_institution'?'active':''?>"><i class="bi bi-building"></i>Kurumlar</a>
            <a href="?page=units" class="<?=$page==='units'||$page==='edit_unit'?'active':''?>"><i class="bi bi-diagram-3"></i>Birimler</a>
            <div class="nav-label mt-2">Puantaj & Döngü</div>
            <a href="?page=timesheet" class="<?=$page==='timesheet'?'active':''?>"><i class="bi bi-table"></i>Puantaj Tablosu</a>
            <a href="?page=bulk_entry" class="<?=$page==='bulk_entry'?'active':''?>"><i class="bi bi-pencil-square"></i>Toplu Giriş</a>
            <a href="?page=templates" class="<?=in_array($page,['templates','template'])?'active':''?>"><i class="bi bi-arrow-repeat"></i>4 Haftalık Şablon</a>
            <a href="?page=leaves" class="<?=$page==='leaves'?'active':''?>"><i class="bi bi-calendar-check"></i>İzin Yönetimi</a>
            <a href="?page=reports" class="<?=$page==='reports'?'active':''?>"><i class="bi bi-bar-chart"></i>Raporlar</a>
            <div class="nav-label mt-2">Sistem</div>
            <a href="?page=holidays" class="<?=$page==='holidays'?'active':''?>"><i class="bi bi-calendar-heart"></i>Tatil Günleri</a>
            <a href="?page=settings" class="<?=$page==='settings'?'active':''?>"><i class="bi bi-gear"></i>Ayarlar</a>
        <?php else: ?>
            <div class="nav-label mt-2">Puantaj</div>
            <a href="?page=my_timesheet" class="<?=$page==='my_timesheet'?'active':''?>"><i class="bi bi-table"></i>Puantaj Tablom</a>
            <a href="?page=timesheet" class="<?=$page==='timesheet'?'active':''?>"><i class="bi bi-people"></i>Tüm Puantaj Tablosu</a>
            <a href="?page=my_leaves" class="<?=$page==='my_leaves'?'active':''?>"><i class="bi bi-calendar-check"></i>İzinlerim</a>
        <?php endif; ?>
        <div class="nav-label mt-2">Hesap</div>
        <a href="?page=profile" class="<?=$page==='profile'?'active':''?>"><i class="bi bi-person-circle"></i>Profilim</a>
        <a href="?action=logout" class="text-danger"><i class="bi bi-box-arrow-left"></i>Çıkış Yap</a>
    </div>
</nav>

<div class="main-content">
    <div class="top-nav">
        <div class="d-flex align-items-center">
            <button class="btn-sidebar-toggle me-3" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
            <h6 class="mb-0 fw-bold text-dark">
                <?php
                $titles=['dashboard'=>'Kontrol Paneli','users'=>'Çalışanlar','institutions'=>'Kurumlar','units'=>'Birimler','timesheet'=>'Puantaj Tablosu','bulk_entry'=>'Toplu Giriş','templates'=>'4 Haftalık Şablonlar','template'=>'Şablon Düzenle','leaves'=>'İzin Yönetimi','reports'=>'Raporlar','holidays'=>'Tatil Günleri','settings'=>'Ayarlar','my_timesheet'=>'Puantaj Tablom','my_leaves'=>'İzinlerim','profile'=>'Profilim','edit_user'=>'Kullanıcı Düzenle','edit_institution'=>'Kurum Düzenle','edit_unit'=>'Birim Düzenle'];
                echo $titles[$page] ?? 'Sayfa';
                ?>
            </h6>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="d-none d-md-inline text-muted small"><?=sanitize($_SESSION['user_name']??'')?></span>
            <div class="user-avatar"><?=strtoupper(mb_substr($_SESSION['user_name']??'U',0,1))?></div>
        </div>
    </div>

    <div class="page-content">
        <?php $fl=getFlash(); if($fl): ?><div class="alert alert-<?=$fl['type']?> alert-dismissible fade show"><i class="bi bi-<?=$fl['type']==='success'?'check-circle':'exclamation-triangle'?> me-2"></i><?=$fl['message']?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<?php
// =====================================================
// DASHBOARD
// =====================================================
if ($page === 'dashboard'):
    if (isAdmin()) {
        $totalUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE role='employee' AND is_active=1")->fetchColumn();
        $totalUnits = $pdo->query("SELECT COUNT(*) FROM units WHERE is_active=1")->fetchColumn();
        $pendingLeaves = $pdo->query("SELECT COUNT(*) FROM leave_records WHERE status='pending'")->fetchColumn();
        $overrides = $pdo->query("SELECT COUNT(*) FROM work_records WHERE work_date=CURDATE()")->fetchColumn();

        // Calculate Leaves for Today and Tomorrow
        $allEmps = $pdo->query("SELECT id, full_name FROM users WHERE is_active=1 AND role='employee' ORDER BY full_name")->fetchAll();
        $empIds = array_column($allEmps, 'id');
        $todayDate = date('Y-m-d');
        $tomorrowDate = date('Y-m-d', strtotime('+1 day'));
        list($dashRecCache, $dashTplCache) = buildRangeCache($todayDate, $tomorrowDate, $empIds);
        
        $leavesToday = [];
        $leavesTomorrow = [];
        foreach($allEmps as $emp) {
            $hasOvr = isset($dashRecCache[$emp['id']][$todayDate]);
            $dayData = $hasOvr ? $dashRecCache[$emp['id']][$todayDate] : getDayStatus($emp['id'], $todayDate, $dashRecCache, $dashTplCache);
            if (($dayData['status'] ?? '') === 'leave') {
                 $leavesToday[] = $emp;
            }
            $hasOvrT = isset($dashRecCache[$emp['id']][$tomorrowDate]);
            $dayDataT = $hasOvrT ? $dashRecCache[$emp['id']][$tomorrowDate] : getDayStatus($emp['id'], $tomorrowDate, $dashRecCache, $dashTplCache);
            if (($dayDataT['status'] ?? '') === 'leave') {
                 $leavesTomorrow[] = $emp;
            }
        }
    } else {
        $uid = currentUserId();
        $myMonthRecs = $pdo->prepare("SELECT COUNT(*) FROM work_records WHERE user_id=? AND MONTH(work_date)=MONTH(CURDATE()) AND YEAR(work_date)=YEAR(CURDATE())");
        $myMonthRecs->execute([$uid]); $myMonthRecs = $myMonthRecs->fetchColumn();
        $myPendingL = $pdo->prepare("SELECT COUNT(*) FROM leave_records WHERE user_id=? AND status='pending'");
        $myPendingL->execute([$uid]); $myPendingL = $myPendingL->fetchColumn();
    }
?>

<?php if (isAdmin()): ?>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-card"><div class="stat-icon mb-2" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8)"><i class="bi bi-people"></i></div>
        <div class="stat-value"><?=$totalUsers?></div><div class="stat-label">Toplam Çalışan</div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card"><div class="stat-icon mb-2" style="background:linear-gradient(135deg,#10b981,#059669)"><i class="bi bi-diagram-3"></i></div>
        <div class="stat-value"><?=$totalUnits?></div><div class="stat-label">Aktif Birim</div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card"><div class="stat-icon mb-2" style="background:linear-gradient(135deg,#f59e0b,#d97706)"><i class="bi bi-hourglass"></i></div>
        <div class="stat-value"><?=$pendingLeaves?></div><div class="stat-label">Bekleyen İzin</div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card"><div class="stat-icon mb-2" style="background:linear-gradient(135deg,#8b5cf6,#6d28d9)"><i class="bi bi-pencil-square"></i></div>
        <div class="stat-value"><?=$overrides?></div><div class="stat-label">Bugün Değişiklik</div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="table-card">
            <div class="card-header"><h6 class="mb-0 fw-bold"><i class="bi bi-info-circle me-2"></i>Sistem Bilgisi</h6></div>
            <div class="p-4">
                <div class="alert alert-info mb-3">
                    <i class="bi bi-arrow-repeat me-2"></i><strong>4 Haftalık Döngü Sistemi Aktif</strong><br>
                    <small>Tüm çalışanlar varsayılan olarak <strong>hafta içi Mevcut</strong>, <strong>hafta sonu Tatil</strong> olarak işaretlenir. 
                    Bu döngü her 4 haftada otomatik tekrar eder. Adminler istedikleri günü değiştirebilir.</small>
                </div>
                <div class="row g-2">
                    <div class="col-md-6"><a href="?page=timesheet" class="btn btn-outline-primary w-100"><i class="bi bi-table me-2"></i>Puantaj Tablosu</a></div>
                    <div class="col-md-6"><a href="?page=templates" class="btn btn-outline-success w-100"><i class="bi bi-arrow-repeat me-2"></i>Şablonları Yönet</a></div>
                    <div class="col-md-6"><a href="?page=bulk_entry" class="btn btn-outline-warning w-100"><i class="bi bi-pencil-square me-2"></i>Toplu Giriş</a></div>
                    <div class="col-md-6"><a href="?page=leaves" class="btn btn-outline-info w-100"><i class="bi bi-calendar-check me-2"></i>İzin Yönetimi</a></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 d-flex flex-column gap-3">
        <div class="table-card">
            <div class="card-header"><h6 class="mb-0 fw-bold">Bekleyen İzinler</h6></div>
            <div class="p-3">
                <?php $pl=$pdo->query("SELECT lr.*,u.full_name FROM leave_records lr LEFT JOIN users u ON lr.user_id=u.id WHERE lr.status='pending' ORDER BY lr.created_at DESC LIMIT 5")->fetchAll(); ?>
                <?php if (empty($pl)): ?><p class="text-muted text-center py-3 mb-0" style="font-size: .85rem">Bekleyen izin yok</p>
                <?php else: foreach($pl as $l): ?>
                    <div class="d-flex justify-content-between align-items-center p-2 mb-1 rounded" style="background:#f8fafc">
                        <div><div class="fw-medium" style="font-size:.82rem"><?=sanitize($l['full_name'])?></div><small class="text-muted"><?=getLeaveTypeText($l['leave_type'])?></small></div>
                        <?=getStatusBadge($l['status'])?>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <div class="table-card">
            <div class="card-header"><h6 class="mb-0 fw-bold text-info"><i class="bi bi-calendar2-x me-2"></i>Bugün İzinli Olanlar</h6></div>
            <div class="p-3">
                <?php if(empty($leavesToday)): ?>
                    <p class="text-muted text-center py-2 mb-0" style="font-size: .85rem">Bugün izinli çalışan bulunmuyor.</p>
                <?php else: ?>
                    <ul class="list-unstyled mb-0" style="font-size: .85rem">
                    <?php foreach($leavesToday as $emp): ?>
                        <li class="border-bottom py-2 d-flex gap-2 align-items-center text-dark"><i class="bi bi-person text-muted"></i> <?=sanitize($emp['full_name'])?></li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-card">
            <div class="card-header"><h6 class="mb-0 fw-bold text-warning"><i class="bi bi-calendar3 me-2"></i>Yarın İzinli Olacaklar</h6></div>
            <div class="p-3">
                <?php if(empty($leavesTomorrow)): ?>
                    <p class="text-muted text-center py-2 mb-0" style="font-size: .85rem">Yarın için izinli çalışan bulunmuyor.</p>
                <?php else: ?>
                    <ul class="list-unstyled mb-0" style="font-size: .85rem">
                    <?php foreach($leavesTomorrow as $emp): ?>
                        <li class="border-bottom py-2 d-flex gap-2 align-items-center text-dark"><i class="bi bi-person text-muted"></i> <?=sanitize($emp['full_name'])?></li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<!-- Employee Dashboard -->
<div class="row g-3 mb-4">
    <div class="col-6"><div class="stat-card"><div class="stat-icon mb-2" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8)"><i class="bi bi-calendar-check"></i></div><div class="stat-value"><?=$myMonthRecs?></div><div class="stat-label">Bu Ay Değişiklik</div></div></div>
    <div class="col-6"><div class="stat-card"><div class="stat-icon mb-2" style="background:linear-gradient(135deg,#f59e0b,#d97706)"><i class="bi bi-hourglass"></i></div><div class="stat-value"><?=$myPendingL?></div><div class="stat-label">Bekleyen İzin</div></div></div>
</div>
<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Puantaj tablonuz 4 haftalık döngü ile otomatik oluşturulur. Hafta içi günler <strong>Mevcut</strong>, hafta sonları <strong>Tatil</strong> olarak işaretlenir.</div>
<?php endif; ?>

<?php
// =====================================================
// PUANTAJ TABLOSU - 4 Haftalık Döngü Destekli
// =====================================================
elseif ($page === 'timesheet'):
    $viewType = $_GET['view_type'] ?? 'monthly';
    $selMonth = (int)($_GET['month'] ?? date('n'));
    $selYear = (int)($_GET['year'] ?? date('Y'));
    $selUnit = (int)($_GET['unit_filter'] ?? 0);
    $selUser = (int)($_GET['user_filter'] ?? 0);
    
    // Haftalık Görünüm Mantığı
    if ($viewType === 'weekly') {
        $weekStart = $_GET['week_start'] ?? '';
        if (!$weekStart) {
            // Bu haftanın Pazartesi gününü bul
            $d = new DateTime();
            $d->setTimestamp(strtotime('monday this week'));
            $weekStart = $d->format('Y-m-d');
        }
        $startDate = $weekStart;
        $endDate = date('Y-m-d', strtotime($weekStart . ' +6 days'));
        $daysToDisplay = 7;
    } else {
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selMonth, $selYear);
        $startDate = sprintf('%04d-%02d-01', $selYear, $selMonth);
        $endDate = sprintf('%04d-%02d-%02d', $selYear, $selMonth, $daysInMonth);
        $daysToDisplay = $daysInMonth;
    }

    $units = $pdo->query("SELECT * FROM units WHERE is_active=1 ORDER BY name")->fetchAll();
    $allEmployees = $pdo->query("SELECT id, full_name FROM users WHERE is_active=1 AND role='employee' ORDER BY full_name")->fetchAll();

    $userWhere = "u.is_active=1";
    $params = [];
    if ($selUnit) { $userWhere .= " AND u.unit_id=?"; $params[] = $selUnit; }
    if ($selUser) { $userWhere .= " AND u.id=?"; $params[] = $selUser; }
    $stmt = $pdo->prepare("SELECT u.id, u.full_name, u.unit_id, un.name as unit_name FROM users u LEFT JOIN units un ON u.unit_id=un.id WHERE $userWhere ORDER BY u.full_name");
    $stmt->execute($params);
    $employees = $stmt->fetchAll();
    $empIds = array_column($employees, 'id');
    
    // Cache oluştur
    if ($viewType === 'weekly') {
        list($recordsCache, $templatesCache) = buildRangeCache($startDate, $endDate, $empIds);
    } else {
        list($recordsCache, $templatesCache) = buildMonthCache($selMonth, $selYear, $empIds);
    }
    
    $holidays = $pdo->query("SELECT date FROM holidays")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 no-print">
    <div class="d-flex gap-2 align-items-center">
        <!-- Görünüm Seçici -->
        <div class="btn-group btn-group-sm me-2">
            <a href="?page=timesheet&view_type=monthly&month=<?=$selMonth?>&year=<?=$selYear?>&unit_filter=<?=$selUnit?>&user_filter=<?=$selUser?>" 
               class="btn <?=$viewType==='monthly'?'btn-primary':'btn-outline-primary'?>">Aylık</a>
            <a href="?page=timesheet&view_type=weekly&unit_filter=<?=$selUnit?>&user_filter=<?=$selUser?>" 
               class="btn <?=$viewType==='weekly'?'btn-primary':'btn-outline-primary'?>">Haftalık</a>
        </div>

        <form method="get" class="d-flex gap-2 flex-wrap align-items-center">
            <input type="hidden" name="page" value="timesheet">
            <input type="hidden" name="view_type" value="<?=$viewType?>">
            
            <?php if ($viewType === 'monthly'): ?>
                <select name="month" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                    <?php for($m=1;$m<=12;$m++): ?><option value="<?=$m?>" <?=$selMonth==$m?'selected':''?>><?=turkishMonth($m)?></option><?php endfor; ?>
                </select>
                <select name="year" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                    <?php for($y=date('Y')-2;$y<=date('Y')+1;$y++): ?><option value="<?=$y?>" <?=$selYear==$y?'selected':''?>><?=$y?></option><?php endfor; ?>
                </select>
            <?php else: ?>
                <!-- Haftalık Navigasyon -->
                <div class="d-flex align-items-center gap-1">
                    <a href="?page=timesheet&view_type=weekly&week_start=<?=date('Y-m-d', strtotime($startDate . ' -7 days'))?>&unit_filter=<?=$selUnit?>&user_filter=<?=$selUser?>" 
                       class="btn btn-sm btn-outline-secondary" title="Önceki Hafta"><i class="bi bi-chevron-left"></i></a>
                    <div class="badge bg-light text-dark border py-2 px-3 fw-medium">
                        <?=formatDate($startDate)?> - <?=formatDate($endDate)?>
                    </div>
                    <a href="?page=timesheet&view_type=weekly&week_start=<?=date('Y-m-d', strtotime($startDate . ' +7 days'))?>&unit_filter=<?=$selUnit?>&user_filter=<?=$selUser?>" 
                       class="btn btn-sm btn-outline-secondary" title="Sonraki Hafta"><i class="bi bi-chevron-right"></i></a>
                    <input type="hidden" name="week_start" value="<?=$startDate?>">
                </div>
            <?php endif; ?>

            <select name="unit_filter" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                <option value="0">Tüm Birimler</option>
                <?php foreach($units as $u): ?><option value="<?=$u['id']?>" <?=$selUnit==$u['id']?'selected':''?>><?=sanitize($u['name'])?></option><?php endforeach; ?>
            </select>
            <select name="user_filter" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                <option value="0">Tüm Çalışanlar</option>
                <?php foreach($allEmployees as $e): ?><option value="<?=$e['id']?>" <?=$selUser==$e['id']?'selected':''?>><?=sanitize($e['full_name'])?></option><?php endforeach; ?>
            </select>
        </form>
    </div>
    <div class="d-flex gap-2">
        <?php if(isAdmin()): ?>
        <a href="?page=bulk_entry&date=<?=$startDate?>&unit_filter=<?=$selUnit?>&user_filter=<?=$selUser?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil-square me-1"></i>Toplu Giriş</a>
        <?php endif; ?>
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>Yazdır</button>
    </div>
</div>

<div class="table-card d-none d-md-block">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
        <h6 class="mb-0 fw-bold">
            <?php if ($viewType === 'monthly'): ?>
                <?=turkishMonth($selMonth)?> <?=$selYear?> Puantaj Tablosu
            <?php else: ?>
                <?=formatDate($startDate)?> - <?=formatDate($endDate)?> Haftalık Puantaj
            <?php endif; ?>
        </h6>
        <small class="text-muted"><i class="bi bi-arrow-repeat me-1"></i>4 haftalık döngü aktif<?=isAdmin()?' · Hücreler tıklanabilir':''?></small>
    </div>
    <div class="timesheet-scroll">
        <table class="table table-bordered table-sm mb-0" style="font-size:.72rem;min-width:<?=180+$daysToDisplay*42?>px">
            <thead>
                <tr>
                    <th style="position:sticky;left:0;background:#f8fafc;z-index:10;min-width:140px">Çalışan</th>
                    <?php for($d=1;$d<=$daysToDisplay;$d++):
                        if ($viewType === 'monthly') {
                            $ds = sprintf('%04d-%02d-%02d', $selYear, $selMonth, $d);
                            $dayNum = $d;
                        } else {
                            $ds = date('Y-m-d', strtotime($startDate . " + ".($d-1)." days"));
                            $dayNum = date('d', strtotime($ds));
                        }
                        $dn = turkishDayShort(date('l', strtotime($ds)));
                        $isWe = in_array(date('N', strtotime($ds)), [6, 7]);
                        $isH = in_array($ds, $holidays);
                    ?>
                        <th class="text-center <?=($isWe||$isH)?'bg-danger bg-opacity-10':''?>" style="min-width:38px;padding:2px">
                            <div><?=$dayNum?></div><div style="font-size:.55rem;font-weight:400"><?=$dn?></div>
                        </th>
                    <?php endfor; ?>
                    <th class="text-center" style="min-width:45px">Top.</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($employees as $emp):
                    $totalDays = 0;
                ?>
                <tr>
                    <td style="position:sticky;left:0;background:#fff;z-index:5;font-weight:500;font-size:.78rem">
                        <?=sanitize($emp['full_name'])?>
                        <br><small class="text-muted"><?=sanitize($emp['unit_name']??'')?></small>
                    </td>
                    <?php for($d=1;$d<=$daysToDisplay;$d++):
                        if ($viewType === 'monthly') {
                            $ds = sprintf('%04d-%02d-%02d', $selYear, $selMonth, $d);
                        } else {
                            $ds = date('Y-m-d', strtotime($startDate . " + ".($d-1)." days"));
                        }
                        
                        // Manuel kayıt var mı?
                        $hasOverride = isset($recordsCache[$emp['id']][$ds]);
                        
                        if ($hasOverride) {
                            $dayData = $recordsCache[$emp['id']][$ds];
                        } else {
                            $dayData = getDayStatus($emp['id'], $ds, $recordsCache, $templatesCache);
                        }
                        
                        $status = $dayData['status'] ?? 'present';
                        $symbol = getStatusSymbol($status);
                        
                        if ($status === 'present') $totalDays++;
                        elseif ($status === 'half_day') $totalDays += 0.5;
                        
                        $overrideClass = $hasOverride ? ' ts-override' : '';
                    ?>
                        <td class="text-center p-0" style="vertical-align:middle">
                            <div class="ts-cell ts-<?=$status?><?=$overrideClass?>"
                                 <?php if(isAdmin()): ?>
                                 onclick="openDayModal(<?=$emp['id']?>,'<?=sanitize($emp['full_name'])?>','<?=$ds?>','<?=$status?>','<?=$dayData['start_time']??''?>','<?=$dayData['end_time']??''?>',<?=$dayData['break_minutes']??0?>,<?=$dayData['overtime_minutes']??0?>,<?=$dayData['unit_id']??$emp['unit_id']??0?>,'<?=sanitize($dayData['notes']??'')?>')"
                                 <?php else: ?>
                                 style="cursor:default"
                                 <?php endif; ?>
                                 title="<?=getStatusText($status)?><?=$hasOverride?' (Manuel)':''?>">
                                <?=$symbol?>
                            </div>
                        </td>
                    <?php endfor; ?>
                    <td class="text-center fw-bold" style="font-size:.82rem"><?=$totalDays?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div> <!-- end timesheet-scroll -->
</div> <!-- end desktop table-card -->

<!-- Mobile View (Grouped by Week) -->
<div class="d-md-none">
    <?php foreach($employees as $emp): 
        $totalDays = 0;
        // Pre-calculate total for header
        for($d=1;$d<=$daysToDisplay;$d++) {
            if ($viewType === 'monthly') {
                $ds = sprintf('%04d-%02d-%02d', $selYear, $selMonth, $d);
            } else {
                $ds = date('Y-m-d', strtotime($startDate . " + ".($d-1)." days"));
            }
            $hasOverride = isset($recordsCache[$emp['id']][$ds]);
            $dayData = $hasOverride ? $recordsCache[$emp['id']][$ds] : getDayStatus($emp['id'], $ds, $recordsCache, $templatesCache);
            $status = $dayData['status'] ?? 'present';
            if ($status === 'present') $totalDays++;
            elseif ($status === 'half_day') $totalDays += 0.5;
        }
    ?>
    <div class="mobile-ts-card">
        <div class="card-header">
            <div>
                <div class="fw-bold text-dark" style="font-size:.9rem"><?=sanitize($emp['full_name'])?></div>
                <small class="text-muted" style="font-size:.7rem"><?=sanitize($emp['unit_name']??'')?></small>
            </div>
            <div class="badge bg-primary rounded-pill"><?=$totalDays?> Gün</div>
        </div>
        <div class="card-body">
            <?php 
            $currentWeek = 1;
            for($d=1;$d<=$daysToDisplay;$d++):
                if ($viewType === 'monthly') {
                    $currentDate = sprintf('%04d-%02d-%02d', $selYear, $selMonth, $d);
                    $dayNum = $d;
                } else {
                    $currentDate = date('Y-m-d', strtotime($startDate . " + ".($d-1)." days"));
                    $dayNum = date('d', strtotime($currentDate));
                }
                $dayOfWeek = date('N', strtotime($currentDate));
                
                // Start a new week block if it's the 1st day of display or a Monday
                if ($d == 1 || $dayOfWeek == 1): ?>
                    <?php if ($d > 1): ?></div></div><?php endif; ?>
                    <div class="mobile-ts-week">
                        <span class="mobile-ts-week-label">
                            <?=$viewType==='monthly' ? $currentWeek.'. Hafta' : 'Haftalık Görünüm'?>
                        </span>
                        <div class="mobile-ts-grid-header">
                            <span class="mobile-ts-day-label">Pt</span>
                            <span class="mobile-ts-day-label">Sa</span>
                            <span class="mobile-ts-day-label">Ça</span>
                            <span class="mobile-ts-day-label">Pe</span>
                            <span class="mobile-ts-day-label">Cu</span>
                            <span class="mobile-ts-day-label">Ct</span>
                            <span class="mobile-ts-day-label">Pa</span>
                        </div>
                        <div class="mobile-ts-grid">
                        <?php 
                        // If it's the beginning of a month view and doesn't start on Monday, add empty spacers
                        if ($viewType === 'monthly' && $d == 1 && $dayOfWeek > 1) {
                            for ($s=1; $s < $dayOfWeek; $s++) {
                                echo '<div></div>';
                            }
                        }
                        ?>
                    <?php $currentWeek++; ?>
                <?php endif; 
                
                $hasOverride = isset($recordsCache[$emp['id']][$currentDate]);
                $dayData = $hasOverride ? $recordsCache[$emp['id']][$currentDate] : getDayStatus($emp['id'], $currentDate, $recordsCache, $templatesCache);
                $status = $dayData['status'] ?? 'present';
                $symbol = getStatusSymbol($status);
                $overrideClass = $hasOverride ? ' ts-override' : '';
                ?>
                <div class="ts-cell-mobile ts-<?=$status?><?=$overrideClass?>"
                     <?php if(isAdmin()): ?>
                     onclick="openDayModal(<?=$emp['id']?>,'<?=sanitize($emp['full_name'])?>','<?=$currentDate?>','<?=$status?>','<?=$dayData['start_time']??''?>','<?=$dayData['end_time']??''?>',<?=$dayData['break_minutes']??0?>,<?=$dayData['overtime_minutes']??0?>,<?=$dayData['unit_id']??$emp['unit_id']??0?>,'<?=sanitize($dayData['notes']??'')?>')"
                     <?php else: ?>
                     style="cursor:default"
                     <?php endif; ?>>
                    <span class="day-num"><?=$dayNum?></span>
                    <span class="symbol"><?=$symbol?></span>
                </div>
            <?php endfor; ?>
            </div></div> <!-- Close last week -->
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="table-card p-3 d-flex flex-wrap gap-2" style="font-size:.75rem">
        <span><span class="badge bg-success">✓</span> Mevcut</span>
        <span><span class="badge bg-danger">✗</span> Devamsız</span>
        <span><span class="badge bg-info">İ</span> İzinli</span>
        <span><span class="badge bg-warning text-dark">H</span> Hasta</span>
        <span><span class="badge bg-secondary">T</span> Tatil</span>
        <span><span class="badge bg-primary">½</span> Yarım Gün</span>
        <span class="ms-2"><span style="display:inline-block;width:14px;height:14px;border:2px solid #f97316;border-radius:3px;vertical-align:middle"></span> Manuel değişiklik</span>
    </div>

<!-- Day Entry Modal -->
<div class="modal fade" id="dayModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="add_work_record">
                <input type="hidden" name="user_id" id="dm_user_id">
                <input type="hidden" name="work_date" id="dm_work_date">
                <input type="hidden" name="month" value="<?=$selMonth?>">
                <input type="hidden" name="year" value="<?=$selYear?>">
                <input type="hidden" name="unit_filter" value="<?=$selUnit?>">
                <input type="hidden" name="user_filter" value="<?=$selUser?>">
                <div class="modal-header"><h5 class="modal-title" id="dm_title">Puantaj</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label">Durum</label><select name="status" id="dm_status" class="form-select"><option value="present">Mevcut</option><option value="absent">Devamsız</option><option value="leave">İzinli</option><option value="sick">Hasta</option><option value="holiday">Tatil</option><option value="half_day">Yarım Gün</option></select></div>
                        <div class="col-6"><label class="form-label">Giriş</label><input type="time" name="start_time" id="dm_start" class="form-control"></div>
                        <div class="col-6"><label class="form-label">Çıkış</label><input type="time" name="end_time" id="dm_end" class="form-control"></div>
                        <div class="col-6"><label class="form-label">Mola (dk)</label><input type="number" name="break_minutes" id="dm_break" class="form-control" value="0" min="0"></div>
                        <div class="col-6"><label class="form-label">F.Mesai (dk)</label><input type="number" name="overtime_minutes" id="dm_overtime" class="form-control" value="0" min="0"></div>
                        <div class="col-12"><label class="form-label">Birim</label><select name="unit_id" id="dm_unit" class="form-select"><option value="">-</option><?php foreach($units as $u): ?><option value="<?=$u['id']?>"><?=sanitize($u['name'])?></option><?php endforeach; ?></select></div>
                        <div class="col-12"><label class="form-label">Not</label><input type="text" name="notes" id="dm_notes" class="form-control"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Kaydet (Manuel Override)</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openDayModal(uid,name,date,status,start,end,brk,ot,unitId,notes) {
    document.getElementById('dm_user_id').value = uid;
    document.getElementById('dm_work_date').value = date;
    document.getElementById('dm_title').textContent = name + ' - ' + date;
    document.getElementById('dm_status').value = status || 'present';
    document.getElementById('dm_start').value = start || '<?=getSetting('work_start_time','08:00')?>';
    document.getElementById('dm_end').value = end || '<?=getSetting('work_end_time','17:00')?>';
    document.getElementById('dm_break').value = brk || 0;
    document.getElementById('dm_overtime').value = ot || 0;
    document.getElementById('dm_unit').value = unitId || '';
    document.getElementById('dm_notes').value = notes || '';
    new bootstrap.Modal(document.getElementById('dayModal')).show();
}
</script>

<?php
// =====================================================
// TOPLU GİRİŞ
// =====================================================
elseif ($page === 'bulk_entry' && isAdmin()):
    $selDate = $_GET['date'] ?? date('Y-m-d');
    $selUnit = (int)($_GET['unit_filter'] ?? 0);
    $units = $pdo->query("SELECT * FROM units WHERE is_active=1 ORDER BY name")->fetchAll();

    $userWhere = "u.is_active=1";
    $params = [];
    if ($selUnit) { $userWhere .= " AND u.unit_id=?"; $params[] = $selUnit; }
    $stmt = $pdo->prepare("SELECT u.id, u.full_name, u.unit_id, un.name as unit_name FROM users u LEFT JOIN units un ON u.unit_id=un.id WHERE $userWhere ORDER BY u.full_name");
    $stmt->execute($params);
    $employees = $stmt->fetchAll();
?>

<div class="no-print mb-3">
    <form method="get" class="d-flex gap-2 flex-wrap">
        <input type="hidden" name="page" value="bulk_entry">
        <input type="date" name="date" value="<?=$selDate?>" class="form-control form-control-sm" style="width:auto" onchange="this.form.submit()">
        <select name="unit_filter" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            <option value="0">Tüm Birimler</option>
            <?php foreach($units as $u): ?><option value="<?=$u['id']?>" <?=$selUnit==$u['id']?'selected':''?>><?=sanitize($u['name'])?></option><?php endforeach; ?>
        </select>
    </form>
</div>

<div class="alert alert-info no-print">
    <i class="bi bi-info-circle me-2"></i>Tüm çalışanlar varsayılan olarak <strong>Mevcut</strong> gösterilir. Sadece değişiklik yaptığınız satırlar kaydedilir.
</div>

<div class="table-card">
    <div class="card-header">
        <h6 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2"></i><?=formatDate($selDate)?> - <?=turkishDay(date('l',strtotime($selDate)))?></h6>
    </div>
    <form method="post">
        <input type="hidden" name="action" value="bulk_work_record">
        <input type="hidden" name="work_date" value="<?=$selDate?>">
        <input type="hidden" name="month" value="<?=date('n',strtotime($selDate))?>">
        <input type="hidden" name="year" value="<?=date('Y',strtotime($selDate))?>">
        <input type="hidden" name="unit_filter" value="<?=$selUnit?>">
        <div class="timesheet-scroll">
            <table class="table table-hover mb-0" style="font-size:.82rem">
                <thead><tr><th>Çalışan</th><th>Birim</th><th>Durum</th><th>Giriş</th><th>Çıkış</th><th>Mola</th><th>F.Mesai</th><th>Not</th></tr></thead>
                <tbody>
                    <?php foreach($employees as $emp):
                        $dayData = getDayStatus($emp['id'], $selDate);
                        $defStart = getSetting('work_start_time','08:00');
                        $defEnd = getSetting('work_end_time','17:00');
                    ?>
                    <tr>
                        <td class="fw-medium"><?=sanitize($emp['full_name'])?></td>
                        <td><select name="users[<?=$emp['id']?>][unit_id]" class="form-select form-select-sm"><option value="">-</option><?php foreach($units as $u): ?><option value="<?=$u['id']?>" <?=($dayData['unit_id']??$emp['unit_id']??0)==$u['id']?'selected':''?>><?=sanitize($u['name'])?></option><?php endforeach; ?></select></td>
                        <td><select name="users[<?=$emp['id']?>][status]" class="form-select form-select-sm">
                            <option value="present" <?=($dayData['status']??'')==='present'?'selected':''?>>Mevcut</option>
                            <option value="absent" <?=($dayData['status']??'')==='absent'?'selected':''?>>Devamsız</option>
                            <option value="leave" <?=($dayData['status']??'')==='leave'?'selected':''?>>İzinli</option>
                            <option value="sick" <?=($dayData['status']??'')==='sick'?'selected':''?>>Hasta</option>
                            <option value="holiday" <?=($dayData['status']??'')==='holiday'?'selected':''?>>Tatil</option>
                            <option value="half_day" <?=($dayData['status']??'')==='half_day'?'selected':''?>>Yarım Gün</option>
                        </select></td>
                        <td><input type="time" name="users[<?=$emp['id']?>][start_time]" class="form-control form-control-sm" value="<?=$dayData['start_time']??$defStart?>" style="width:95px"></td>
                        <td><input type="time" name="users[<?=$emp['id']?>][end_time]" class="form-control form-control-sm" value="<?=$dayData['end_time']??$defEnd?>" style="width:95px"></td>
                        <td><input type="number" name="users[<?=$emp['id']?>][break_minutes]" class="form-control form-control-sm" value="<?=$dayData['break_minutes']??60?>" min="0" style="width:65px"></td>
                        <td><input type="number" name="users[<?=$emp['id']?>][overtime_minutes]" class="form-control form-control-sm" value="<?=$dayData['overtime_minutes']??0?>" min="0" style="width:65px"></td>
                        <td><input type="text" name="users[<?=$emp['id']?>][notes]" class="form-control form-control-sm" value="<?=sanitize($dayData['notes']??'')?>" style="width:100px"></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="p-3"><button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-2"></i>Değişiklikleri Kaydet</button></div>
    </form>
</div>

<?php
// =====================================================
// 4 HAFTALIK ŞABLONLAR LİSTESİ
// =====================================================
elseif ($page === 'templates' && isAdmin()):
    $employees = $pdo->query("SELECT u.id, u.full_name, u.unit_id, un.name as unit_name,
        (SELECT COUNT(*) FROM work_templates WHERE user_id=u.id) as tpl_count
        FROM users u LEFT JOIN units un ON u.unit_id=un.id 
        WHERE u.is_active=1 ORDER BY u.full_name")->fetchAll();
?>

<div class="alert alert-info">
    <i class="bi bi-arrow-repeat me-2"></i><strong>4 Haftalık Döngü Sistemi:</strong> Her çalışan için 28 günlük (4 hafta) bir şablon tanımlanır. Bu şablon sürekli tekrar eder. Varsayılan: Hafta içi = Mevcut, Hafta sonu = Tatil.
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted"><?=count($employees)?> çalışan</span>
    <a href="?action=generate_all_templates" class="btn btn-sm btn-success" onclick="return confirm('Tüm çalışanlar için eksik şablonlar oluşturulsun mu?')">
        <i class="bi bi-magic me-1"></i>Tüm Şablonları Oluştur
    </a>
</div>

<div class="table-card">
    <div class="timesheet-scroll">
        <table class="table table-hover mb-0">
            <thead><tr><th>Çalışan</th><th>Birim</th><th>Şablon Günleri</th><th>İşlem</th></tr></thead>
            <tbody>
                <?php foreach($employees as $e): ?>
                <tr>
                    <td class="fw-medium"><?=sanitize($e['full_name'])?></td>
                    <td><?=sanitize($e['unit_name']??'-')?></td>
                    <td>
                        <?php if ($e['tpl_count'] >= 28): ?>
                            <span class="badge bg-success"><?=$e['tpl_count']?>/28 gün tanımlı</span>
                        <?php elseif ($e['tpl_count'] > 0): ?>
                            <span class="badge bg-warning text-dark"><?=$e['tpl_count']?>/28 gün</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Tanımsız (varsayılan kullanılır)</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?page=template&uid=<?=$e['id']?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Şablonu Düzenle</a>
                        <a href="?action=reset_user_template&uid=<?=$e['id']?>" class="btn btn-sm btn-outline-danger ms-1" onclick="return confirm('Bu çalışanın 4 haftalık şablonu varsayılanlara (Hafta içi: Mevcut, Hafta sonu: Tatil) döndürülecektir. Emin misiniz?')">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Sıfırla
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// =====================================================
// TEK ÇALIŞAN ŞABLON DÜZENLE
// =====================================================
elseif ($page === 'template' && isAdmin()):
    $uid = (int)($_GET['uid'] ?? 0);
    $user = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $user->execute([$uid]);
    $user = $user->fetch();
    if (!$user) { setFlash('danger','Kullanıcı bulunamadı!'); redirect('templates'); }
    
    // Şablon yoksa oluştur
    ensureTemplate($uid);
    
    $tpls = $pdo->prepare("SELECT * FROM work_templates WHERE user_id=? ORDER BY day_index");
    $tpls->execute([$uid]);
    $templates = [];
    foreach($tpls->fetchAll() as $t) { $templates[$t['day_index']] = $t; }
    
    $units = $pdo->query("SELECT * FROM units WHERE is_active=1 ORDER BY name")->fetchAll();
    
    $dayNames = ['Pazartesi','Salı','Çarşamba','Perşembe','Cuma','Cumartesi','Pazar'];
?>

<div class="mb-3">
    <a href="?page=templates" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Geri</a>
</div>

<div class="table-card">
    <div class="card-header">
        <h6 class="mb-0 fw-bold"><i class="bi bi-arrow-repeat me-2"></i><?=sanitize($user['full_name'])?> - 4 Haftalık Şablon</h6>
    </div>
    <div class="p-4">
        <form method="post">
            <input type="hidden" name="action" value="update_template">
            <input type="hidden" name="user_id" value="<?=$uid?>">
            
            <?php for($week=0; $week<4; $week++): ?>
            <h6 class="fw-bold mt-<?=$week>0?'4':'0'?> mb-3">
                <span class="badge bg-primary me-2"><?=$week+1?>. Hafta</span>
            </h6>
            <div class="row g-2">
                <?php for($dayInWeek=0; $dayInWeek<7; $dayInWeek++):
                    $dayIdx = $week * 7 + $dayInWeek;
                    $tpl = $templates[$dayIdx] ?? null;
                    $isWeekend = ($dayInWeek >= 5);
                    $curStatus = $tpl['status'] ?? ($isWeekend ? 'holiday' : 'present');
                ?>
                <div class="col-md-6 col-lg-4 col-xl-3">
                    <div class="tpl-day <?=$isWeekend?'weekend':''?>">
                        <div class="fw-bold mb-2" style="font-size:.82rem">
                            <i class="bi bi-calendar3 me-1"></i><?=$dayNames[$dayInWeek]?>
                            <small class="text-muted">(Gün <?=$dayIdx+1?>)</small>
                        </div>
                        <div class="mb-2">
                            <select name="template[<?=$dayIdx?>][status]" class="form-select form-select-sm">
                                <option value="present" <?=$curStatus==='present'?'selected':''?>>Mevcut</option>
                                <option value="absent" <?=$curStatus==='absent'?'selected':''?>>Devamsız</option>
                                <option value="leave" <?=$curStatus==='leave'?'selected':''?>>İzinli</option>
                                <option value="sick" <?=$curStatus==='sick'?'selected':''?>>Hasta</option>
                                <option value="holiday" <?=$curStatus==='holiday'?'selected':''?>>Tatil</option>
                                <option value="half_day" <?=$curStatus==='half_day'?'selected':''?>>Yarım Gün</option>
                            </select>
                        </div>
                        <div class="row g-1 mb-1">
                            <div class="col-6"><input type="time" name="template[<?=$dayIdx?>][start_time]" class="form-control form-control-sm" value="<?=$tpl['start_time']??($isWeekend?'':'08:00')?>"></div>
                            <div class="col-6"><input type="time" name="template[<?=$dayIdx?>][end_time]" class="form-control form-control-sm" value="<?=$tpl['end_time']??($isWeekend?'':'17:00')?>"></div>
                        </div>
                        <div class="row g-1">
                            <div class="col-6"><input type="number" name="template[<?=$dayIdx?>][break_minutes]" class="form-control form-control-sm" value="<?=$tpl['break_minutes']??($isWeekend?0:60)?>" min="0" placeholder="Mola dk"></div>
                            <div class="col-6">
                                <select name="template[<?=$dayIdx?>][unit_id]" class="form-select form-select-sm">
                                    <option value="">Birim</option>
                                    <?php foreach($units as $u): ?><option value="<?=$u['id']?>" <?=($tpl['unit_id']??$user['unit_id']??0)==$u['id']?'selected':''?>><?=sanitize($u['name'])?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <input type="text" name="template[<?=$dayIdx?>][notes]" class="form-control form-control-sm mt-1" value="<?=sanitize($tpl['notes']??'')?>" placeholder="Not">
                    </div>
                </div>
                <?php endfor; ?>
            </div>
            <?php endfor; ?>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-2"></i>Şablonu Kaydet</button>
                <a href="?page=templates" class="btn btn-secondary ms-2">İptal</a>
            </div>
        </form>
    </div>
</div>

<?php
// =====================================================
// KULLANICI YÖNETİMİ (kısa - aynı mantık)
// =====================================================
elseif ($page === 'users' && isAdmin()):
    $users = $pdo->query("SELECT u.*, un.name as unit_name FROM users u LEFT JOIN units un ON u.unit_id=un.id ORDER BY u.full_name")->fetchAll();
    $units = $pdo->query("SELECT u.*, i.name as inst_name FROM units u LEFT JOIN institutions i ON u.institution_id=i.id WHERE u.is_active=1 ORDER BY i.name,u.name")->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted"><?=count($users)?> kullanıcı</span>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="bi bi-plus-lg me-1"></i>Yeni Kullanıcı</button>
</div>
<div class="table-card"><div class="timesheet-scroll">
    <table class="table table-hover mb-0">
        <thead><tr><th>Ad Soyad</th><th>Kullanıcı</th><th>Birim</th><th>Telefon</th><th>Rol</th><th>Durum</th><th>İşlem</th></tr></thead>
        <tbody>
            <?php foreach($users as $u): ?>
            <tr>
                <td class="fw-medium"><?=sanitize($u['full_name'])?></td>
                <td><code><?=sanitize($u['username'])?></code></td>
                <td><?=sanitize($u['unit_name']??'-')?></td>
                <td><?=sanitize($u['phone']??'-')?></td>
                <td><?=$u['role']==='admin'?'<span class="badge bg-danger">Admin</span>':'<span class="badge bg-primary">Çalışan</span>'?></td>
                <td><?=$u['is_active']?'<span class="badge bg-success">Aktif</span>':'<span class="badge bg-secondary">Pasif</span>'?></td>
                <td>
                    <a href="?page=edit_user&id=<?=$u['id']?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                    <?php if ($u['id']!==currentUserId()): ?><a href="?action=delete_user&id=<?=$u['id']?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Silmek istediğinize emin misiniz?')"><i class="bi bi-trash"></i></a><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div></div>
<div class="modal fade" id="addUserModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="post"><input type="hidden" name="action" value="admin_add_user">
        <div class="modal-header"><h5 class="modal-title">Yeni Kullanıcı</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><div class="row g-3">
            <div class="col-md-6"><label class="form-label">Ad Soyad *</label><input type="text" name="full_name" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Kullanıcı Adı *</label><input type="text" name="username" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Şifre *</label><input type="password" name="password" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Rol</label><select name="role" class="form-select"><option value="employee">Çalışan</option><option value="admin">Admin</option></select></div>
            <div class="col-md-6"><label class="form-label">E-posta</label><input type="email" name="email" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Telefon</label><input type="tel" name="phone" class="form-control"></div>
            <div class="col-md-6"><label class="form-label">Birim</label><select name="unit_id" class="form-select"><option value="">Seçiniz</option><?php foreach($units as $u): ?><option value="<?=$u['id']?>"><?=sanitize($u['inst_name'].' - '.$u['name'])?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Pozisyon</label><input type="text" name="position" class="form-control"></div>
        </div></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button><button type="submit" class="btn btn-primary">Ekle</button></div>
    </form>
</div></div></div>

<?php
elseif ($page === 'edit_user' && isAdmin()):
    $id=(int)($_GET['id']??0);
    $user=$pdo->prepare("SELECT * FROM users WHERE id=?"); $user->execute([$id]); $user=$user->fetch();
    if (!$user) { setFlash('danger','Bulunamadı!'); redirect('users'); }
    $units=$pdo->query("SELECT u.*,i.name as inst_name FROM units u LEFT JOIN institutions i ON u.institution_id=i.id WHERE u.is_active=1 ORDER BY i.name,u.name")->fetchAll();
?>
<div class="table-card"><div class="card-header"><h6 class="mb-0 fw-bold">Düzenle: <?=sanitize($user['full_name'])?></h6></div>
<div class="p-4"><form method="post"><input type="hidden" name="action" value="admin_edit_user"><input type="hidden" name="id" value="<?=$user['id']?>">
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Ad Soyad *</label><input type="text" name="full_name" class="form-control" value="<?=sanitize($user['full_name'])?>" required></div>
        <div class="col-md-6"><label class="form-label">Kullanıcı Adı *</label><input type="text" name="username" class="form-control" value="<?=sanitize($user['username'])?>" required></div>
        <div class="col-md-6"><label class="form-label">E-posta</label><input type="email" name="email" class="form-control" value="<?=sanitize($user['email']??'')?>"></div>
        <div class="col-md-6"><label class="form-label">Telefon</label><input type="tel" name="phone" class="form-control" value="<?=sanitize($user['phone']??'')?>"></div>
        <div class="col-md-6"><label class="form-label">TC No</label><input type="text" name="tc_no" class="form-control" value="<?=sanitize($user['tc_no']??'')?>" maxlength="11"></div>
        <div class="col-md-6"><label class="form-label">Doğum Tarihi</label><input type="date" name="birth_date" class="form-control" value="<?=$user['birth_date']??''?>"></div>
        <div class="col-md-6"><label class="form-label">Cinsiyet</label><select name="gender" class="form-select"><option value="">Seçiniz</option><option <?=($user['gender']??'')==='Erkek'?'selected':''?>>Erkek</option><option <?=($user['gender']??'')==='Kadın'?'selected':''?>>Kadın</option><option <?=($user['gender']??'')==='Diğer'?'selected':''?>>Diğer</option></select></div>
        <div class="col-md-6"><label class="form-label">Birim</label><select name="unit_id" class="form-select"><option value="">Seçiniz</option><?php foreach($units as $u): ?><option value="<?=$u['id']?>" <?=($user['unit_id']??0)==$u['id']?'selected':''?>><?=sanitize($u['inst_name'].' - '.$u['name'])?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">Pozisyon</label><input type="text" name="position" class="form-control" value="<?=sanitize($user['position']??'')?>"></div>
        <div class="col-md-6"><label class="form-label">Rol</label><select name="role" class="form-select"><option value="employee" <?=$user['role']==='employee'?'selected':''?>>Çalışan</option><option value="admin" <?=$user['role']==='admin'?'selected':''?>>Admin</option></select></div>
        <div class="col-md-6"><label class="form-label">Yeni Şifre</label><input type="password" name="new_password" class="form-control" placeholder="Boş=değişmez"></div>
        <div class="col-12"><label class="form-label">Adres</label><textarea name="address" class="form-control" rows="2"><?=sanitize($user['address']??'')?></textarea></div>
        <div class="col-12"><div class="form-check"><input type="checkbox" name="is_active" class="form-check-input" <?=$user['is_active']?'checked':''?>><label class="form-check-label">Aktif</label></div></div>
    </div>
    <div class="mt-4"><button type="submit" class="btn btn-primary">Güncelle</button> <a href="?page=users" class="btn btn-secondary">Geri</a></div>
</form></div></div>

<?php
// =====================================================
// KURUMLAR
// =====================================================
elseif ($page === 'institutions' && isAdmin()):
    $insts=$pdo->query("SELECT i.*,(SELECT COUNT(*) FROM units WHERE institution_id=i.id) as uc FROM institutions i ORDER BY i.name")->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted"><?=count($insts)?> kurum</span>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addInstM"><i class="bi bi-plus-lg me-1"></i>Yeni Kurum</button>
</div>
<div class="row g-3">
    <?php foreach($insts as $i): ?>
    <div class="col-md-6 col-lg-4"><div class="stat-card">
        <h6 class="fw-bold"><?=sanitize($i['name'])?></h6>
        <small class="text-muted"><?=$i['uc']?> birim</small>
        <?php if($i['description']): ?><p class="small text-muted mt-1 mb-0"><?=sanitize($i['description'])?></p><?php endif; ?>
        <div class="mt-2"><a href="?page=edit_institution&id=<?=$i['id']?>" class="btn btn-sm btn-outline-primary">Düzenle</a> <a href="?action=delete_institution&id=<?=$i['id']?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Emin misiniz?')">Sil</a></div>
    </div></div>
    <?php endforeach; ?>
</div>
<div class="modal fade" id="addInstM" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post"><input type="hidden" name="action" value="add_institution">
    <div class="modal-header"><h5 class="modal-title">Yeni Kurum</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Kurum Adı *</label><input type="text" name="name" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Açıklama</label><textarea name="description" class="form-control" rows="2"></textarea></div>
        <div class="mb-3"><label class="form-label">Adres</label><textarea name="address" class="form-control" rows="2"></textarea></div>
        <div class="row g-3"><div class="col-6"><label class="form-label">Telefon</label><input type="tel" name="phone" class="form-control"></div><div class="col-6"><label class="form-label">E-posta</label><input type="email" name="email" class="form-control"></div></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button><button type="submit" class="btn btn-primary">Ekle</button></div>
</form></div></div></div>

<?php
elseif ($page === 'edit_institution' && isAdmin()):
    $id=(int)($_GET['id']??0); $inst=$pdo->prepare("SELECT * FROM institutions WHERE id=?"); $inst->execute([$id]); $inst=$inst->fetch();
    if (!$inst) { setFlash('danger','Bulunamadı!'); redirect('institutions'); }
?>
<div class="table-card"><div class="p-4"><form method="post"><input type="hidden" name="action" value="edit_institution"><input type="hidden" name="id" value="<?=$inst['id']?>">
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Kurum Adı *</label><input type="text" name="name" class="form-control" value="<?=sanitize($inst['name'])?>" required></div>
        <div class="col-md-6"><label class="form-label">E-posta</label><input type="email" name="email" class="form-control" value="<?=sanitize($inst['email']??'')?>"></div>
        <div class="col-md-6"><label class="form-label">Telefon</label><input type="tel" name="phone" class="form-control" value="<?=sanitize($inst['phone']??'')?>"></div>
        <div class="col-12"><label class="form-label">Açıklama</label><textarea name="description" class="form-control" rows="2"><?=sanitize($inst['description']??'')?></textarea></div>
        <div class="col-12"><label class="form-label">Adres</label><textarea name="address" class="form-control" rows="2"><?=sanitize($inst['address']??'')?></textarea></div>
        <div class="col-12"><div class="form-check"><input type="checkbox" name="is_active" class="form-check-input" <?=$inst['is_active']?'checked':''?>><label class="form-check-label">Aktif</label></div></div>
    </div>
    <div class="mt-4"><button type="submit" class="btn btn-primary">Güncelle</button> <a href="?page=institutions" class="btn btn-secondary">Geri</a></div>
</form></div></div>

<?php
// =====================================================
// BİRİMLER
// =====================================================
elseif ($page === 'units' && isAdmin()):
    $units=$pdo->query("SELECT u.*,i.name as iname,(SELECT COUNT(*) FROM users WHERE unit_id=u.id AND is_active=1) as uc FROM units u LEFT JOIN institutions i ON u.institution_id=i.id ORDER BY i.name,u.name")->fetchAll();
    $insts=$pdo->query("SELECT * FROM institutions WHERE is_active=1 ORDER BY name")->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted"><?=count($units)?> birim</span>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUnitM"><i class="bi bi-plus-lg me-1"></i>Yeni Birim</button>
</div>
<div class="row g-3">
    <?php foreach($units as $u): ?>
    <div class="col-md-6 col-lg-4"><div class="stat-card">
        <h6 class="fw-bold"><?=sanitize($u['name'])?></h6>
        <small class="text-muted"><i class="bi bi-building me-1"></i><?=sanitize($u['iname']??'-')?> · <?=$u['uc']?> çalışan</small>
        <div class="mt-2"><a href="?page=edit_unit&id=<?=$u['id']?>" class="btn btn-sm btn-outline-primary">Düzenle</a> <a href="?action=delete_unit&id=<?=$u['id']?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Emin misiniz?')">Sil</a></div>
    </div></div>
    <?php endforeach; ?>
</div>
<div class="modal fade" id="addUnitM" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post"><input type="hidden" name="action" value="add_unit">
    <div class="modal-header"><h5 class="modal-title">Yeni Birim</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Kurum *</label><select name="institution_id" class="form-select" required><option value="">Seçiniz</option><?php foreach($insts as $i): ?><option value="<?=$i['id']?>"><?=sanitize($i['name'])?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label class="form-label">Birim Adı *</label><input type="text" name="name" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Açıklama</label><textarea name="description" class="form-control" rows="2"></textarea></div>
        <div class="row g-3"><div class="col-6"><label class="form-label">Telefon</label><input type="tel" name="phone" class="form-control"></div><div class="col-6"><label class="form-label">Sorumlu</label><input type="text" name="manager_name" class="form-control"></div></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button><button type="submit" class="btn btn-primary">Ekle</button></div>
</form></div></div></div>

<?php
elseif ($page === 'edit_unit' && isAdmin()):
    $id=(int)($_GET['id']??0); $unit=$pdo->prepare("SELECT * FROM units WHERE id=?"); $unit->execute([$id]); $unit=$unit->fetch();
    if (!$unit) { setFlash('danger','Bulunamadı!'); redirect('units'); }
    $insts=$pdo->query("SELECT * FROM institutions WHERE is_active=1 ORDER BY name")->fetchAll();
?>
<div class="table-card"><div class="p-4"><form method="post"><input type="hidden" name="action" value="edit_unit"><input type="hidden" name="id" value="<?=$unit['id']?>">
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Kurum *</label><select name="institution_id" class="form-select" required><?php foreach($insts as $i): ?><option value="<?=$i['id']?>" <?=$unit['institution_id']==$i['id']?'selected':''?>><?=sanitize($i['name'])?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">Birim Adı *</label><input type="text" name="name" class="form-control" value="<?=sanitize($unit['name'])?>" required></div>
        <div class="col-md-6"><label class="form-label">Telefon</label><input type="tel" name="phone" class="form-control" value="<?=sanitize($unit['phone']??'')?>"></div>
        <div class="col-md-6"><label class="form-label">Sorumlu</label><input type="text" name="manager_name" class="form-control" value="<?=sanitize($unit['manager_name']??'')?>"></div>
        <div class="col-12"><label class="form-label">Açıklama</label><textarea name="description" class="form-control" rows="2"><?=sanitize($unit['description']??'')?></textarea></div>
        <div class="col-12"><div class="form-check"><input type="checkbox" name="is_active" class="form-check-input" <?=$unit['is_active']?'checked':''?>><label class="form-check-label">Aktif</label></div></div>
    </div>
    <div class="mt-4"><button type="submit" class="btn btn-primary">Güncelle</button> <a href="?page=units" class="btn btn-secondary">Geri</a></div>
</form></div></div>

<?php
// =====================================================
// İZİN YÖNETİMİ
// =====================================================
elseif ($page === 'leaves' && isAdmin()):
    $leaves=$pdo->query("SELECT lr.*,u.full_name FROM leave_records lr LEFT JOIN users u ON lr.user_id=u.id ORDER BY lr.created_at DESC")->fetchAll();
    $emps=$pdo->query("SELECT id,full_name FROM users WHERE is_active=1 ORDER BY full_name")->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted"><?=count($leaves)?> kayıt</span>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLeaveM"><i class="bi bi-plus-lg me-1"></i>İzin Ekle</button>
</div>
<div class="table-card"><div class="timesheet-scroll"><table class="table table-hover mb-0">
    <thead><tr><th>Çalışan</th><th>Tür</th><th>Başlangıç</th><th>Bitiş</th><th>Gün</th><th>Durum</th><th>İşlem</th></tr></thead>
    <tbody>
        <?php foreach($leaves as $l): ?>
        <tr>
            <td class="fw-medium"><?=sanitize($l['full_name'])?></td>
            <td><?=getLeaveTypeText($l['leave_type'])?></td>
            <td><?=formatDate($l['start_date'])?></td><td><?=formatDate($l['end_date'])?></td>
            <td><?=$l['total_days']?></td><td><?=getStatusBadge($l['status'])?></td>
            <td>
                <?php if($l['status']==='pending'): ?>
                <form method="post" class="d-inline"><input type="hidden" name="action" value="approve_leave"><input type="hidden" name="id" value="<?=$l['id']?>"><input type="hidden" name="status" value="approved"><button class="btn btn-sm btn-success"><i class="bi bi-check"></i></button></form>
                <form method="post" class="d-inline"><input type="hidden" name="action" value="approve_leave"><input type="hidden" name="id" value="<?=$l['id']?>"><input type="hidden" name="status" value="rejected"><button class="btn btn-sm btn-danger"><i class="bi bi-x"></i></button></form>
                <?php endif; ?>
                <a href="?action=delete_leave&id=<?=$l['id']?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Emin misiniz?')"><i class="bi bi-trash"></i></a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table></div></div>
<div class="modal fade" id="addLeaveM" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post"><input type="hidden" name="action" value="admin_add_leave">
    <div class="modal-header"><h5 class="modal-title">İzin Ekle</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Çalışan *</label><select name="user_id" class="form-select" required><option value="">Seçiniz</option><?php foreach($emps as $e): ?><option value="<?=$e['id']?>"><?=sanitize($e['full_name'])?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label class="form-label">İzin Türü</label><select name="leave_type" class="form-select"><option value="annual">Yıllık İzin</option><option value="sick">Hastalık</option><option value="unpaid">Ücretsiz</option><option value="other">Diğer</option></select></div>
        <div class="row g-3"><div class="col-6"><label class="form-label">Başlangıç *</label><input type="date" name="start_date" class="form-control" required></div><div class="col-6"><label class="form-label">Bitiş *</label><input type="date" name="end_date" class="form-control" required></div></div>
        <div class="mb-3 mt-3"><label class="form-label">Durum</label><select name="status" class="form-select"><option value="approved">Onaylı</option><option value="pending">Beklemede</option></select></div>
        <div class="mb-3"><label class="form-label">Açıklama</label><textarea name="reason" class="form-control" rows="2"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button><button type="submit" class="btn btn-primary">Ekle</button></div>
</form></div></div></div>

<?php
// =====================================================
// RAPORLAR
// =====================================================
elseif ($page === 'reports' && isAdmin()):
    $selMonth = (int)($_GET['month'] ?? date('n'));
    $selYear = (int)($_GET['year'] ?? date('Y'));
    $selUnit = (int)($_GET['unit_filter'] ?? 0);
    $selUser = (int)($_GET['user_filter'] ?? 0);
    
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    
    if ($startDate && $endDate) {
        $repStart = $startDate;
        $repEnd = $endDate;
        $title = formatDate($repStart) . " - " . formatDate($repEnd) . " Raporu";
    } else {
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selMonth, $selYear);
        $repStart = sprintf('%04d-%02d-01', $selYear, $selMonth);
        $repEnd = sprintf('%04d-%02d-%02d', $selYear, $selMonth, $daysInMonth);
        $title = turkishMonth($selMonth) . " $selYear Raporu";
    }

    $units = $pdo->query("SELECT * FROM units WHERE is_active=1 ORDER BY name")->fetchAll();
    $allEmployees = $pdo->query("SELECT id, full_name FROM users WHERE is_active=1 AND role='employee' ORDER BY full_name")->fetchAll();

    $userWhere = "u.is_active=1";
    $params = [];
    if ($selUnit) { $userWhere .= " AND u.unit_id=?"; $params[] = $selUnit; }
    if ($selUser) { $userWhere .= " AND u.id=?"; $params[] = $selUser; }

    $stmt = $pdo->prepare("SELECT u.id,u.full_name,un.name as unit_name FROM users u LEFT JOIN units un ON u.unit_id=un.id WHERE $userWhere ORDER BY u.full_name");
    $stmt->execute($params);
    $employees = $stmt->fetchAll();
    
    $empIds = array_column($employees, 'id');
    list($recCache, $tplCache) = buildRangeCache($repStart, $repEnd, $empIds);
?>
<div class="no-print mb-3">
    <form method="get" class="d-flex gap-2 flex-wrap bg-white p-3 rounded shadow-sm border">
        <input type="hidden" name="page" value="reports">
        <div class="d-flex align-items-center gap-2 border-end pe-3 me-2">
            <span class="small fw-bold text-muted">Aylık:</span>
            <select name="month" class="form-select form-select-sm" style="width:auto">
                <?php for($m=1;$m<=12;$m++): ?><option value="<?=$m?>" <?=$selMonth==$m?'selected':''?>><?=turkishMonth($m)?></option><?php endfor; ?>
            </select>
            <select name="year" class="form-select form-select-sm" style="width:auto">
                <?php for($y=date('Y')-2;$y<=date('Y')+1;$y++): ?><option value="<?=$y?>" <?=$selYear==$y?'selected':''?>><?=$y?></option><?php endfor; ?>
            </select>
        </div>
        <div class="d-flex align-items-center gap-2 border-end pe-3 me-2">
            <span class="small fw-bold text-muted">Aralık:</span>
            <input type="date" name="start_date" class="form-control form-control-sm" value="<?=$startDate?>" style="width:auto">
            <input type="date" name="end_date" class="form-control form-control-sm" value="<?=$endDate?>" style="width:auto">
        </div>
        <select name="unit_filter" class="form-select form-select-sm" style="width:auto">
            <option value="0">Tüm Birimler</option>
            <?php foreach($units as $u): ?><option value="<?=$u['id']?>" <?=$selUnit==$u['id']?'selected':''?>><?=sanitize($u['name'])?></option><?php endforeach; ?>
        </select>
        <select name="user_filter" class="form-select form-select-sm" style="width:auto">
            <option value="0">Tüm Çalışanlar</option>
            <?php foreach($allEmployees as $e): ?><option value="<?=$e['id']?>" <?=$selUser==$e['id']?'selected':''?>><?=sanitize($e['full_name'])?></option><?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-sm btn-primary px-3 shadow-sm"><i class="bi bi-filter me-1"></i>Filtrele</button>
        <div class="ms-auto d-flex gap-2">
            <a href="?<?=http_build_query(array_merge($_GET, ['action'=>'export_word']))?>" class="btn btn-sm btn-outline-primary shadow-sm" title="Word Olarak İndir">
                <i class="bi bi-file-earmark-word me-1"></i>Word
            </a>
            <button type="button" onclick="exportReportToCSV('<?=sanitize($title)?>.csv')" class="btn btn-sm btn-success shadow-sm">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i>CSV
            </button>
            <button onclick="window.print()" type="button" class="btn btn-sm btn-outline-secondary shadow-sm">
                <i class="bi bi-printer me-1"></i>Yazdır
            </button>
        </div>
    </form>
</div>
<div class="table-card">
    <div class="card-header"><h6 class="mb-0 fw-bold"><?=sanitize($title)?> özet raporu</h6></div>
    <div class="timesheet-scroll"><table class="table table-hover mb-0" id="reportTable">
        <thead><tr><th>Çalışan</th><th>Birim</th><th class="text-center">Mevcut</th><th class="text-center">Devamsız</th><th class="text-center">İzinli</th><th class="text-center">Hasta</th><th class="text-center">Tatil</th><th class="text-center">½ Gün</th></tr></thead>
        <tbody>
            <?php foreach($employees as $emp):
                $counts = ['present'=>0,'absent'=>0,'leave'=>0,'sick'=>0,'holiday'=>0,'half_day'=>0];
                $cur = new DateTime($repStart);
                $last = new DateTime($repEnd);
                while($cur <= $last) {
                    $ds = $cur->format('Y-m-d');
                    $hasOvr = isset($recCache[$emp['id']][$ds]);
                    $dayData = $hasOvr ? $recCache[$emp['id']][$ds] : getDayStatus($emp['id'], $ds, $recCache, $tplCache);
                    $st = $dayData['status'] ?? 'present';
                    if (isset($counts[$st])) $counts[$st]++;
                    $cur->modify('+1 day');
                }
            ?>
            <tr>
                <td class="fw-medium"><?=sanitize($emp['full_name'])?></td>
                <td><?=sanitize($emp['unit_name']??'-')?></td>
                <td class="text-center"><span class="badge bg-success"><?=$counts['present']?></span></td>
                <td class="text-center"><span class="badge bg-danger"><?=$counts['absent']?></span></td>
                <td class="text-center"><span class="badge bg-info"><?=$counts['leave']?></span></td>
                <td class="text-center"><span class="badge bg-warning text-dark"><?=$counts['sick']?></span></td>
                <td class="text-center"><span class="badge bg-secondary"><?=$counts['holiday']?></span></td>
                <td class="text-center"><span class="badge bg-primary"><?=$counts['half_day']?></span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table></div>
</div>

<script>
function exportReportToCSV(filename) {
    var csv = [];
    var rows = document.querySelectorAll(".table-card table tr");
    
    for (var i = 0; i < rows.length; i++) {
        var row = [], cols = rows[i].querySelectorAll("td, th");
        for (var j = 0; j < cols.length; j++) {
            var text = cols[j].innerText.trim();
            // CSV formatına uygun hale getir (tırnaklar ve ayraçlar)
            text = '"' + text.replace(/"/g, '""') + '"';
            row.push(text);
        }
        csv.push(row.join(";"));
    }

    // UTF-8 BOM ekle (Excel'in Türkçe karakterleri doğru tanıması için)
    var csvContent = "\uFEFF" + csv.join("\n");
    var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement("a");
    if (link.download !== undefined) {
        var url = URL.createObjectURL(blob);
        link.setAttribute("href", url);
        link.setAttribute("download", filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}
</script>

<?php
// =====================================================
// TATİL GÜNLERİ
// =====================================================
elseif ($page === 'holidays' && isAdmin()):
    $holidays=$pdo->query("SELECT * FROM holidays ORDER BY date")->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted"><?=count($holidays)?> tatil</span>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHolM"><i class="bi bi-plus-lg me-1"></i>Tatil Ekle</button>
</div>
<div class="table-card"><table class="table table-hover mb-0">
    <thead><tr><th>Tatil</th><th>Tarih</th><th>Tekrarlı</th><th>İşlem</th></tr></thead>
    <tbody><?php foreach($holidays as $h): ?>
        <tr><td class="fw-medium"><?=sanitize($h['name'])?></td><td><?=formatDate($h['date'])?></td><td><?=$h['is_recurring']?'<span class="badge bg-success">Her Yıl</span>':'<span class="badge bg-secondary">Tek</span>'?></td><td><a href="?action=delete_holiday&id=<?=$h['id']?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Emin misiniz?')"><i class="bi bi-trash"></i></a></td></tr>
    <?php endforeach; ?></tbody>
</table></div>
<div class="modal fade" id="addHolM" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post"><input type="hidden" name="action" value="add_holiday">
    <div class="modal-header"><h5 class="modal-title">Tatil Ekle</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Tatil Adı *</label><input type="text" name="name" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">Tarih *</label><input type="date" name="date" class="form-control" required></div>
        <div class="form-check"><input type="checkbox" name="is_recurring" class="form-check-input"><label class="form-check-label">Her yıl tekrarlansın</label></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button><button type="submit" class="btn btn-primary">Ekle</button></div>
</form></div></div></div>

<?php
// =====================================================
// AYARLAR
// =====================================================
elseif ($page === 'settings' && isAdmin()):
    $settings=$pdo->query("SELECT * FROM settings ORDER BY id")->fetchAll();
    $cycleStart=$pdo->query("SELECT cycle_start_date FROM cycle_config ORDER BY id LIMIT 1")->fetchColumn();
    $employees=$pdo->query("SELECT id, full_name FROM users WHERE is_active=1 ORDER BY full_name")->fetchAll();
?>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="table-card"><div class="card-header"><h6 class="mb-0 fw-bold"><i class="bi bi-gear me-2"></i>Genel Ayarlar</h6></div>
        <div class="p-4"><form method="post"><input type="hidden" name="action" value="update_settings">
            <div class="row g-3">
                <?php foreach($settings as $s): ?>
                <div class="col-md-6">
                    <label class="form-label"><?=sanitize($s['description']?:$s['setting_key'])?></label>
                    <?php if($s['setting_key']==='theme_color'): ?><input type="color" name="settings[<?=$s['setting_key']?>]" class="form-control form-control-color w-100" value="<?=sanitize($s['setting_value'])?>">
                    <?php else: ?><input type="text" name="settings[<?=$s['setting_key']?>]" class="form-control" value="<?=sanitize($s['setting_value'])?>">
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-4"><button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-2"></i>Kaydet</button></div>
        </form></div></div>
    </div>
    <div class="col-lg-4">
        <div class="table-card mb-3"><div class="card-header"><h6 class="mb-0 fw-bold"><i class="bi bi-arrow-repeat me-2"></i>Döngü Ayarı</h6></div>
        <div class="p-4">
            <form method="post">
                <input type="hidden" name="action" value="update_cycle">
                <label class="form-label">4 Haftalık Döngü Başlangıcı (Pazartesi olmalı)</label>
                <input type="date" name="cycle_start_date" class="form-control mb-2" value="<?=$cycleStart?>">
                <small class="text-muted d-block mb-3">Bu tarihten itibaren her 28 günde bir döngü tekrar eder.</small>
                <button type="submit" class="btn btn-primary btn-sm">Güncelle</button>
            </form>
        </div></div>
        
        <div class="table-card mb-3"><div class="card-header"><h6 class="mb-0 fw-bold"><i class="bi bi-trash3 me-2 text-danger"></i>Puantaj Kayıtlarını Sil (Sıfırla)</h6></div>
        <div class="p-4">
            <form method="post" onsubmit="return confirm('Seçilen kriterlere uygun TÜM puantaj kayıtları kalıcı olarak silinecek ve şablona geri dönecektir. Devam etmek istiyor musunuz?')">
                <input type="hidden" name="action" value="delete_work_records">
                <div class="mb-3">
                    <label class="form-label small">Çalışan Seçimi</label>
                    <select name="user_id" class="form-select form-select-sm">
                        <option value="0">--- TÜM ÇALIŞANLAR ---</option>
                        <?php foreach($employees as $emp): ?>
                            <option value="<?=$emp['id']?>"><?=sanitize($emp['full_name'])?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small">Başlangıç</label>
                        <input type="date" name="start_date" class="form-control form-control-sm">
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Bitiş</label>
                        <input type="date" name="end_date" class="form-control form-control-sm">
                    </div>
                </div>
                <div class="alert alert-warning p-2 mb-3" style="font-size: 0.7rem;">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Boş bırakılan tarih alanları "tüm zamanlar" anlamına gelir.
                </div>
                
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="delete_leaves" id="delLeavesCheck">
                    <label class="form-check-label small" for="delLeavesCheck">
                        Onaylı izinleri (İzin Yönetimi) de sil
                    </label>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="reset_templates" id="resTplCheck">
                    <label class="form-check-label small text-danger" for="resTplCheck">
                        Şablon (döngü) ayarlarını varsayılana döndür
                    </label>
                </div>

                <button type="submit" class="btn btn-danger w-100 btn-sm">
                    <i class="bi bi-trash3 me-2"></i>Seçilen Kayıtları Sil
                </button>
            </form>
        </div></div>
    </div>
</div>

<?php
// =====================================================
// ÇALIŞAN: PUANTAJ TABLOM
// =====================================================
elseif ($page === 'my_timesheet' && !isAdmin()):
    $uid=currentUserId();
    $selMonth=(int)($_GET['month']??date('n')); $selYear=(int)($_GET['year']??date('Y'));
    $daysInMonth=cal_days_in_month(CAL_GREGORIAN,$selMonth,$selYear);
    list($recCache,$tplCache)=buildMonthCache($selMonth,$selYear,[$uid]);
?>
<div class="no-print mb-3">
    <form method="get" class="d-flex gap-2"><input type="hidden" name="page" value="my_timesheet">
        <select name="month" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()"><?php for($m=1;$m<=12;$m++): ?><option value="<?=$m?>" <?=$selMonth==$m?'selected':''?>><?=turkishMonth($m)?></option><?php endfor; ?></select>
        <select name="year" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()"><?php for($y=date('Y')-2;$y<=date('Y')+1;$y++): ?><option value="<?=$y?>" <?=$selYear==$y?'selected':''?>><?=$y?></option><?php endfor; ?></select>
    </form>
</div>
<div class="row g-3">
    <!-- Desktop View -->
    <div class="col-12 d-none d-md-block">
        <div class="table-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><?=turkishMonth($selMonth)?> <?=$selYear?> Puantaj Tablom</h6>
                <small class="text-muted"><i class="bi bi-info-circle me-1"></i>4 haftalık döngüye göre görüntüler</small>
            </div>
            <div class="timesheet-scroll">
                <table class="table table-bordered table-sm mb-0" style="font-size:.72rem">
                    <thead>
                        <tr>
                            <th style="background:#f8fafc;min-width:140px">Bilgi</th>
                            <?php for($d=1;$d<=$daysInMonth;$d++):
                                $ds=sprintf('%04d-%02d-%02d',$selYear,$selMonth,$d);
                                $dn=turkishDayShort(date('l',strtotime($ds)));
                                $isWe=in_array(date('N',strtotime($ds)),[6,7]);
                            ?>
                                <th class="text-center <?=$isWe?'bg-danger bg-opacity-10':''?>" style="min-width:38px;padding:2px">
                                    <div><?=$d?></div><div style="font-size:.55rem;font-weight:400"><?=$dn?></div>
                                </th>
                            <?php endfor; ?>
                            <th class="text-center">Top.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $totalDays = 0;
                        ?>
                        <tr>
                            <td class="fw-medium">Puantaj / Durum</td>
                            <?php for($d=1;$d<=$daysInMonth;$d++):
                                $ds=sprintf('%04d-%02d-%02d',$selYear,$selMonth,$d);
                                $hasOvr=isset($recCache[$uid][$ds]);
                                $dayData=$hasOvr?$recCache[$uid][$ds]:getDayStatus($uid,$ds,$recCache,$tplCache);
                                $status = $dayData['status'] ?? 'present';
                                $symbol = getStatusSymbol($status);
                                if ($status === 'present') $totalDays++;
                                elseif ($status === 'half_day') $totalDays += 0.5;
                            ?>
                                <td class="text-center p-0">
                                    <div class="ts-cell ts-<?=$status?><?=$hasOvr?' ts-override':''?>" 
                                         style="cursor:default"
                                         title="<?=getStatusText($status)?><?=$hasOvr?' (Değişiklik)':''?>">
                                        <?=$symbol?>
                                    </div>
                                </td>
                            <?php endfor; ?>
                            <td class="text-center fw-bold"><?=$totalDays?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Mobile View -->
    <div class="col-12 d-md-none">
        <div class="mobile-ts-card">
            <div class="card-header">
                <div class="fw-bold"><?=turkishMonth($selMonth)?> <?=$selYear?></div>
                <?php 
                $mTotal = 0;
                for($d=1;$d<=$daysInMonth;$d++) {
                    $ds=sprintf('%04d-%02d-%02d',$selYear,$selMonth,$d);
                    $hasOvr=isset($recCache[$uid][$ds]);
                    $dayData=$hasOvr?$recCache[$uid][$ds]:getDayStatus($uid,$ds,$recCache,$tplCache);
                    if (($dayData['status']??'') === 'present') $mTotal++;
                    elseif (($dayData['status']??'') === 'half_day') $mTotal += 0.5;
                }
                ?>
                <div class="badge bg-primary rounded-pill"><?=$mTotal?> Gün</div>
            </div>
            <div class="card-body">
                <?php 
                $currentWeek = 1;
                for($d=1;$d<=$daysInMonth;$d++):
                    $ds=sprintf('%04d-%02d-%02d',$selYear,$selMonth,$d);
                    $dayOfWeek = date('N', strtotime($ds));
                    
                    if ($d == 1 || $dayOfWeek == 1): ?>
                        <?php if ($d > 1): ?></div></div><?php endif; ?>
                        <div class="mobile-ts-week">
                            <span class="mobile-ts-week-label"><?=$currentWeek?>. Hafta</span>
                            <div class="mobile-ts-grid-header">
                                <span class="mobile-ts-day-label">Pt</span>
                                <span class="mobile-ts-day-label">Sa</span>
                                <span class="mobile-ts-day-label">Ça</span>
                                <span class="mobile-ts-day-label">Pe</span>
                                <span class="mobile-ts-day-label">Cu</span>
                                <span class="mobile-ts-day-label">Ct</span>
                                <span class="mobile-ts-day-label">Pa</span>
                            </div>
                            <div class="mobile-ts-grid">
                            <?php if ($d == 1 && $dayOfWeek > 1) { for ($s=1; $s < $dayOfWeek; $s++) { echo '<div></div>'; } } ?>
                        <?php $currentWeek++; ?>
                    <?php endif; 
                    
                    $hasOvr=isset($recCache[$uid][$ds]);
                    $dayData=$hasOvr?$recCache[$uid][$ds]:getDayStatus($uid,$ds,$recCache,$tplCache);
                    $status = $dayData['status'] ?? 'present';
                    $symbol = getStatusSymbol($status);
                    ?>
                    <div class="ts-cell-mobile ts-<?=$status?><?=$hasOvr?' ts-override':''?>" style="cursor:default">
                        <span class="day-num"><?=$d?></span>
                        <span class="symbol"><?=$symbol?></span>
                    </div>
                <?php endfor; ?>
                </div></div>
            </div>
        </div>
    </div>
</div>

<div class="table-card p-3 d-flex flex-wrap gap-2 mt-3" style="font-size:.75rem">
    <span><span class="badge bg-success">✓</span> Mevcut</span>
    <span><span class="badge bg-danger">✗</span> Devamsız</span>
    <span><span class="badge bg-info">İ</span> İzinli</span>
    <span><span class="badge bg-warning text-dark">H</span> Hasta</span>
    <span><span class="badge bg-secondary">T</span> Tatil</span>
    <span><span class="badge bg-primary">½</span> Yarım Gün</span>
    <span class="ms-2"><span style="display:inline-block;width:14px;height:14px;border:2px solid #f97316;border-radius:3px;vertical-align:middle"></span> Manuel değişiklik</span>
</div>

<?php
// =====================================================
// ÇALIŞAN: İZİNLERİM
// =====================================================
elseif ($page === 'my_leaves'):
    $uid=currentUserId();
    $myL=$pdo->prepare("SELECT * FROM leave_records WHERE user_id=? ORDER BY created_at DESC"); $myL->execute([$uid]); $myL=$myL->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted"><?=count($myL)?> izin</span>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#reqLeaveM"><i class="bi bi-plus-lg me-1"></i>İzin Talep Et</button>
</div>
<div class="table-card"><table class="table table-hover mb-0">
    <thead><tr><th>Tür</th><th>Başlangıç</th><th>Bitiş</th><th>Gün</th><th>Durum</th></tr></thead>
    <tbody>
        <?php if(empty($myL)): ?><tr><td colspan="5" class="text-center text-muted py-4">Henüz izin kaydı yok</td></tr>
        <?php else: foreach($myL as $l): ?>
        <tr><td><?=getLeaveTypeText($l['leave_type'])?></td><td><?=formatDate($l['start_date'])?></td><td><?=formatDate($l['end_date'])?></td><td><?=$l['total_days']?></td><td><?=getStatusBadge($l['status'])?></td></tr>
        <?php endforeach; endif; ?>
    </tbody>
</table></div>
<div class="modal fade" id="reqLeaveM" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post"><input type="hidden" name="action" value="request_leave">
    <div class="modal-header"><h5 class="modal-title">İzin Talep Et</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">İzin Türü</label><select name="leave_type" class="form-select"><option value="annual">Yıllık İzin</option><option value="sick">Hastalık</option><option value="unpaid">Ücretsiz</option><option value="other">Diğer</option></select></div>
        <div class="row g-3"><div class="col-6"><label class="form-label">Başlangıç *</label><input type="date" name="start_date" class="form-control" required></div><div class="col-6"><label class="form-label">Bitiş *</label><input type="date" name="end_date" class="form-control" required></div></div>
        <div class="mt-3"><label class="form-label">Açıklama</label><textarea name="reason" class="form-control" rows="3"></textarea></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button><button type="submit" class="btn btn-primary">Gönder</button></div>
</form></div></div></div>

<?php
// =====================================================
// PROFİLİM
// =====================================================
elseif ($page === 'profile'):
    $uid=currentUserId();
    $user=$pdo->prepare("SELECT u.*,un.name as unit_name FROM users u LEFT JOIN units un ON u.unit_id=un.id WHERE u.id=?"); $user->execute([$uid]); $user=$user->fetch();
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="stat-card text-center">
            <div class="user-avatar mx-auto mb-3" style="width:70px;height:70px;font-size:1.8rem"><?=strtoupper(mb_substr($user['full_name'],0,1))?></div>
            <h5 class="fw-bold"><?=sanitize($user['full_name'])?></h5>
            <p class="text-muted small mb-1"><?=sanitize($user['position']??'-')?></p>
            <p class="text-muted small"><?=sanitize($user['unit_name']??'-')?></p>
            <span class="badge <?=$user['role']==='admin'?'bg-danger':'bg-primary'?>"><?=$user['role']==='admin'?'Admin':'Çalışan'?></span>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="table-card"><div class="card-header"><h6 class="mb-0 fw-bold">Profil Güncelle</h6></div>
        <div class="p-4"><form method="post"><input type="hidden" name="action" value="update_profile">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Ad Soyad *</label><input type="text" name="full_name" class="form-control" value="<?=sanitize($user['full_name'])?>" required></div>
                <div class="col-md-6"><label class="form-label">E-posta</label><input type="email" name="email" class="form-control" value="<?=sanitize($user['email']??'')?>"></div>
                <div class="col-md-6"><label class="form-label">Telefon</label><input type="tel" name="phone" class="form-control" value="<?=sanitize($user['phone']??'')?>"></div>
                <div class="col-12"><label class="form-label">Adres</label><textarea name="address" class="form-control" rows="2"><?=sanitize($user['address']??'')?></textarea></div>
            </div>
            <hr><h6 class="fw-bold mb-3">Şifre Değiştir</h6>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Mevcut Şifre</label><input type="password" name="current_password" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Yeni Şifre</label><input type="password" name="new_password" class="form-control"></div>
            </div>
            <div class="mt-4"><button type="submit" class="btn btn-primary">Güncelle</button></div>
        </form></div></div>
    </div>
</div>

<?php endif; ?>

    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar() {
    document.getElementById('sidebar')?.classList.toggle('show');
    document.getElementById('sidebarOverlay')?.classList.toggle('show');
}
window.addEventListener('resize', function() {
    if (window.innerWidth >= 992) {
        document.getElementById('sidebar')?.classList.remove('show');
        document.getElementById('sidebarOverlay')?.classList.remove('show');
    }
});
document.querySelectorAll('.alert-dismissible').forEach(function(a) {
    setTimeout(function() { bootstrap.Alert.getOrCreateInstance(a).close(); }, 5000);
});
</script>
</body>
</html>