<?php
    require_once(dirname(__FILE__) . '/../db_conn.php');

function get_board($page, $postsPerPage) {
    global $conn;

    if (!isset($conn)) {
        die("Database connection failed");
    }

    if (!isset($_SESSION['user_id'])) {
        return [];
    }

    $offset = ($page - 1) * $postsPerPage;

    $sql = "SELECT * FROM board ORDER BY date DESC LIMIT ? OFFSET ?";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 'ii', $postsPerPage, $offset);

        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            $board = [];

            if (mysqli_num_rows($result) > 0) {
                while($row = mysqli_fetch_assoc($result)) {
                    array_push($board, $row);
                }
            } else {
                echo "No posts found.";
            }
            mysqli_stmt_close($stmt);
            return $board;

        } else {
            echo "Error executing query: " . mysqli_stmt_error($stmt);
        }
        exit;
    } else {
        echo "Error preparing statement: " . mysqli_stmt_error($stmt);
    }
    exit;
}

function get_total_pages($postsPerPage) {
    global $conn;

    $sql = "SELECT COUNT(*) AS total FROM board";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            $total = mysqli_fetch_assoc($result)['total'];
            return ceil($total / $postsPerPage);
        } else {
            echo "Error: " . mysqli_error($conn);
        }
        exit;
    }
}

function get_board_detail_by_id($id) {
    global $conn;

    $sql = "SELECT b.*, f.file_name FROM board b LEFT JOIN files f ON b.id = f.board_id WHERE b.id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);

    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            return $row;
        }
    } else {
        echo "Error: " . mysqli_error($conn);
    }

    return null;
}

function write_board($title, $content, $file) {
    global $conn;

    if (!isset($_SESSION['user_id'])) {
        echo '<script>
            alert("글을 작성할 권한이 없습니다.");
            location.href = "../index.php";
            </script>';
        exit;
    }

    $now = date('Y-m-d H:i:s');

    $sql = "INSERT INTO board (user_id, author, title, content, date, views) VALUES (?, ?, ?, ?, ?, 0)";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 'issss', $_SESSION['user_id'], $_SESSION['user_name'], $title, $content, $now);

        if (mysqli_stmt_execute($stmt)) {
            $last_id = mysqli_insert_id($conn);

            if ($file && $file['error'] === UPLOAD_ERR_OK) {
                if (!upload_file($file, $last_id)) {
                    $delete_files_sql = "DELETE FROM files WHERE board_id = ?";
                    if ($delete_files_stmt = mysqli_prepare($conn, $delete_files_sql)) {
                        mysqli_stmt_bind_param($delete_files_stmt, 'i', $last_id);
                        mysqli_stmt_execute($delete_files_stmt);
                    }

                    $delete_sql = "DELETE FROM board WHERE id = ?";
                    if ($delete_stmt = mysqli_prepare($conn, $delete_sql)) {
                        mysqli_stmt_bind_param($delete_stmt, 'i', $last_id);
                        mysqli_stmt_execute($delete_stmt);
                    }

                    $_SESSION['title'] = $title;
                    $_SESSION['content'] = $content;

                    echo '<script>
                    alert("파일 업로드에 실패하여 게시글 작성이 취소되었습니다.");
                    location.href = "board_write.php";
                    </script>';
                    exit;
                }
            }

            unset($_SESSION['title']);
            unset($_SESSION['content']);

            echo '<script>
                alert("게시글이 성공적으로 작성되었습니다.");
                location.href = "board.php";
                </script>';
        } else {
            echo "Error: " . mysqli_error($conn);
        }
        exit;
    }
}


function search_board($searchOrder, $searchKeyword, $page, $postsPerPage) {
    global $conn;

    // 컬럼 이름 화이트 리스트
    $allowed_columns = ['author', 'title', 'content'];
    if (!in_array($searchOrder, $allowed_columns)) {
        die('Invalid search order');
    }

    // 검색어를 포함하는 게시글을 검색하는 SQL 쿼리를 작성합니다.
    // 페이징과 내림차순 정렬 기능을 추가합니다.
    $offset = ($page - 1) * $postsPerPage;
    $sql = "SELECT * FROM board WHERE $searchOrder LIKE ? ORDER BY date DESC LIMIT ? OFFSET ?";

    // 쿼리를 준비합니다.
    if ($stmt = mysqli_prepare($conn, $sql)) {
        // 검색어를 바인딩합니다.
        $searchKeyword = '%' . $searchKeyword . '%';
        mysqli_stmt_bind_param($stmt, 'sii', $searchKeyword, $postsPerPage, $offset);

        // 쿼리를 실행합니다.
        if (mysqli_stmt_execute($stmt)) {
            // 쿼리 결과를 가져옵니다.
            $result = mysqli_stmt_get_result($stmt);
            $board = [];

            if (mysqli_num_rows($result) > 0) {
                while($row = mysqli_fetch_assoc($result)) {
                    array_push($board, $row);
                }
            }
            mysqli_stmt_close($stmt);
            return $board;
        } else {
            echo "Error: " . mysqli_error($conn);
        }
        exit;
    }
}

function get_replies($board_id) {
    global $conn;

    $sql = "SELECT * FROM reply WHERE board_id = ? ORDER BY regdate ASC";
    $replies = [];

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 'i', $board_id);

        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);

            if ($result && mysqli_num_rows($result) > 0) {
                while($row = mysqli_fetch_assoc($result)) {
                    array_push($replies, $row);
                }
            }
        } else {
            echo "Error: " . mysqli_error($conn);
        }
    }

    return $replies;
}

function write_reply($board_id, $user_id, $user_name, $content, $current_time) {
    global $conn;

    $sql = "INSERT INTO reply (board_id, user_id, writer, content, regdate) VALUES (?, ?, ?, ?, ?)";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 'iisss', $board_id, $user_id, $user_name, $content, $current_time);

        if (mysqli_stmt_execute($stmt)) {
            return true;
        } else {
            echo "Error: " . mysqli_error($conn);
        }
    }

    return false;
}

function delete_reply($reply_id) {
    global $conn;

    $sql = "DELETE FROM reply WHERE idx = ?";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 'i', $reply_id);

        if (mysqli_stmt_execute($stmt)) {
            return true;
        } else {
            echo "Error: " . mysqli_error($conn);
        }
    }

    return false;
}

function edit_reply($reply_id, $content) {
    global $conn;

    $sql = "UPDATE reply SET content = ? WHERE idx = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'si', $content, $reply_id);
    
    return mysqli_stmt_execute($stmt);
}

function get_file_path($id, $file_name) {
    global $conn;

    $sql = "SELECT file_path FROM files WHERE board_id = ? AND file_name = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'is', $id, $file_name);

    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            return $row['file_path'];
        }
    } else {
        echo "Error: " . mysqli_error($conn);
    }

    return null;
}

function get_files_by_board_id($board_id) {
    global $conn;

    $sql = "SELECT * FROM files WHERE board_id = ?";

    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 'i', $board_id);

        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            
            $files = array();
            while ($row = mysqli_fetch_assoc($result)) {
                $files[] = $row;
            }

            return $files;
        } else {
            echo "Error: " . mysqli_error($conn);
        }
    }

    return array();
}

function upload_file($file, $board_id) {
    global $conn;

    $fileTmpPath = $file['tmp_name'];
    $fileName = $file['name'];
    $fileType = mime_content_type($fileTmpPath);

    // 허용할 파일 확장자와 MIME 타입 목록을 정의
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'ppt', 'pptx'];
    $allowedMimeTypes = [
        'image/jpeg', 'image/png', 'image/gif', 
        'application/pdf', 
        'application/msword', 
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation'
    ];

    // 파일 확장자 추출
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // 확장자와 MIME 타입을 검증
    if (!in_array($fileExtension, $allowedExtensions) || !in_array($fileType, $allowedMimeTypes)) {
        // Save form data in session
        $_SESSION['title'] = $_POST['title'];
        $_SESSION['content'] = $_POST['content'];

        echo '<script>
        alert("허용되지 않는 파일 형식입니다.");
        location.href = "board_write.php";
        </script>';
        return false;
    }

    // 웹 서버의 문서 루트 경로를 가져옴
    $uploadFileDir = $_SERVER['DOCUMENT_ROOT'] . '/board/uploaded_files/' . $board_id . '/';

    // 디렉토리가 없다면 생성
    if (!is_dir($uploadFileDir)) {
        if (!mkdir($uploadFileDir, 0777, true)) {
            $error = error_get_last();
            echo '<script>
            alert("디렉토리 생성 오류: ' . $error['message'] . '");
            location.href = "board_write.php";
            </script>';
            return false;
        }
    }

    $dest_path = $uploadFileDir . $fileName;

    if (move_uploaded_file($fileTmpPath, $dest_path)) {
        $sql = "INSERT INTO files (board_id, file_name, file_path) VALUES (?, ?, ?)";

        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, 'iss', $board_id, $fileName, $dest_path);

            if (!mysqli_stmt_execute($stmt)) {
                echo "Error: " . mysqli_error($conn);
                return false;
            }
        }
    } else {
        $error = error_get_last();
        echo '<script>
        alert("파일 업로드 오류: ' . $error['message'] . '");
        location.href = "board_write.php";
        </script>';
        return false;
    }

    return true;
}


function delete_file($file_id) {
    global $conn;

    // 먼저, 삭제할 파일의 정보를 가져옵니다.
    $sql = "SELECT * FROM files WHERE file_id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 'i', $file_id);
        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            $file = mysqli_fetch_assoc($result);
            
            // 파일이 실제로 서버에 존재하는지 확인하고, 존재한다면 삭제합니다.
            if (file_exists($file['file_path'])) {
                unlink($file['file_path']);
            }
        } else {
            echo "Error: " . mysqli_error($conn);
            return;
        }
    }

    // 이제 파일 정보를 데이터베이스에서 삭제합니다.
    $sql = "DELETE FROM files WHERE file_id = ?";
    if ($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, 'i', $file_id);
        if (!mysqli_stmt_execute($stmt)) {
            echo "Error: " . mysqli_error($conn);
        }
    }
}
?>
