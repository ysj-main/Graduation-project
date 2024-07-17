<?php
include "../db_conn.php";
date_default_timezone_set('Asia/Seoul'); // 서울 시간대 설정

session_start(); // 세션 시작

$id = $_POST['id'];
$pw = $_POST['pw'];

// SQL 인젝션 방지를 위한 prepared statement 사용
$sql = $conn->prepare("SELECT * FROM Users WHERE user_name=?");
$sql->bind_param("s", $id);
$sql->execute();
$result = $sql->get_result();
$row = $result->fetch_assoc();

// 비밀번호 확인 (해시된 비밀번호 비교)
if (!$row || !password_verify($pw, $row['user_password'])) { // 일치하는 아이디 없음 또는 비밀번호 불일치
    echo "<script>
            alert(\"일치하는 아이디가 없거나 아이디 또는 비밀번호가 틀렸습니다\");
            location.href='login.html';
          </script>";
} else {
    // 세션 파기
    session_unset(); // 모든 세션 변수 제거
    session_destroy(); // 세션 파괴

    // 세션 재시작 및 재발급
    session_start();
    session_regenerate_id(true); // 세션 ID 재발급
    $_SESSION['user_id'] = $row['user_id'];
    $_SESSION['user_name'] = $row['user_name'];
    $_SESSION['email'] = $row['email'];
    $_SESSION['role'] = $row['role'];
    
    // 로그인 성공 시, last_login 업데이트
    $current_time = date('Y-m-d H:i:s');
    $update_sql = $conn->prepare("UPDATE Users SET last_login=? WHERE user_name=?");
    $update_sql->bind_param("ss", $current_time, $id);
    $update_sql->execute();
    $update_sql->close();

    header("Location: ../index.php");
    exit;
}

$sql->close();
$conn->close();
?>
