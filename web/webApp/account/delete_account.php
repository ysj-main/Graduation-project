<?php
$lifetime = 1800;
session_set_cookie_params($lifetime);
session_start();

include "../db_conn.php";

if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('로그인이 필요한 서비스 입니다.');";
    echo "window.location.href='../index.php';</script>";
    exit;
}

$user_id = $_POST['user_id'];

$conn->begin_transaction();

try {
    // Family 테이블에서 해당 user_id를 참조하는 레코드 삭제
    $sql = "DELETE FROM Family WHERE user_id=? OR family_user_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $user_id);
    if (!$stmt->execute()) {
        throw new Exception("Family 테이블에서 레코드 삭제 실패: " . $stmt->error);
    }
    $stmt->close();

    // Cards 테이블에서 해당 user_id를 참조하는 레코드 삭제
    $sql = "DELETE FROM Cards WHERE user_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        throw new Exception("Cards 테이블에서 레코드 삭제 실패: " . $stmt->error);
    }
    $stmt->close();

    // reply 테이블에서 해당 user_id를 참조하는 레코드 삭제
    $sql = "DELETE FROM reply WHERE user_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        throw new Exception("reply 테이블에서 레코드 삭제 실패: " . $stmt->error);
    }
    $stmt->close();

    // board 테이블에서 user_id에 맞는 board_id 가져오기
    $sql = "SELECT id FROM board WHERE user_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        throw new Exception("board 테이블에서 board_id 가져오기 실패: " . $stmt->error);
    }
    $stmt->bind_result($board_id);
    $board_ids = [];
    while ($stmt->fetch()) {
        $board_ids[] = $board_id;
    }
    $stmt->close();

    // 가져온 board_id와 연결된 files의 레코드 삭제
    $sql = "DELETE FROM files WHERE board_id=?";
    $stmt = $conn->prepare($sql);
    foreach ($board_ids as $board_id) {
        $stmt->bind_param("i", $board_id);
        if (!$stmt->execute()) {
            throw new Exception("files 테이블에서 레코드 삭제 실패: " . $stmt->error);
        }
    }
    $stmt->close();

    // board 테이블에서 해당 user_id를 참조하는 레코드 삭제
    $sql = "DELETE FROM board WHERE user_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        throw new Exception("board 테이블에서 레코드 삭제 실패: " . $stmt->error);
    }
    $stmt->close();

    // Users 테이블에서 사용자 계정 삭제
    $sql = "DELETE FROM Users WHERE user_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        throw new Exception("Users 테이블에서 레코드 삭제 실패: " . $stmt->error);
    }
    $stmt->close();

    $conn->commit();

    session_destroy();
    echo "<script>alert('계정 삭제가 완료되었습니다.');";
    echo "window.location.href='../index.php';</script>";
} catch (Exception $exception) {
    $conn->rollback();

    // 상세 오류 메시지 출력
    echo "<script>alert('계정 삭제를 실패하였습니다: " . addslashes($exception->getMessage()) . "');";
    echo "window.location.href='mypage.php';</script>";
}

$conn->close();
exit;
?>
