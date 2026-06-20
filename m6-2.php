<?php
session_start();

// 1. データベース接続設定
$dsn = 'mysql:dbname=your_dbname;host=localhost;charset=utf8';
$user = 'your_username';
$password = 'your_password';

try {
    $pdo = new PDO($dsn, $user, $password, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));

    // 【スコア1：データベースに2テーブル以上】
    // ① ユーザー管理テーブル
    $pdo->query("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        emp_no VARCHAR(32) UNIQUE,
        name VARCHAR(32),
        password VARCHAR(255)
    )");

    // ② 投稿（ナレッジ）管理テーブル
    $pdo->query("CREATE TABLE IF NOT EXISTS posts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        content TEXT,
        created_at DATETIME
    )");

    // 【スコア1：自作関数(function)の定義と使用】
    // 投稿一覧を取得する関数を自作してコードをスッキリさせます
    function getPosts($pdo) {
        $sql = "SELECT posts.content, posts.created_at, users.name 
                FROM posts JOIN users ON posts.user_id = users.id 
                ORDER BY posts.created_at DESC";
        return $pdo->query($sql)->fetchAll();
    }

    // --- ログアウト処理 ---
    if (isset($_GET['action']) && $_GET['action'] === 'logout') {
        $_SESSION = array();
        session_destroy();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // --- 新規登録処理 ---
    if (isset($_POST['register'])) {
        $emp_no = $_POST['emp_no'];
        $name = $_POST['name'];
        $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (emp_no, name, password) VALUES (:emp_no, :name, :password)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':emp_no', $emp_no, PDO::PARAM_STR);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':password', $pass, PDO::PARAM_STR);
        $stmt->execute();
        $msg = "登録が完了しました。下からログインしてください。";
    }

    // --- ログイン処理 ---
    if (isset($_POST['login'])) {
        $emp_no = $_POST['login_emp_no'];
        $login_pass = $_POST['login_password'];
        
        $sql = "SELECT * FROM users WHERE emp_no = :emp_no";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':emp_no', $emp_no, PDO::PARAM_STR);
        $stmt->execute();
        $user_data = $stmt->fetch();

        if ($user_data && password_verify($login_pass, $user_data['password'])) {
            $_SESSION['user_id'] = $user_data['id'];
            $_SESSION['name'] = $user_data['name'];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $msg = "社員番号かパスワードが違います。";
        }
    }

    // --- 投稿処理 ---
    if (isset($_POST['post_content']) && !empty($_SESSION['user_id'])) {
        $content = $_POST['content'];
        $sql = "INSERT INTO posts (user_id, content, created_at) VALUES (:user_id, :content, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->bindValue(':content', $content, PDO::PARAM_STR);
        $stmt->execute();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

} catch (PDOException $e) {
    $msg = "データベースエラー: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>Call-Sync ナレッジ共有</title>
    <style>
        body { font-family: sans-serif; background-color: #f0f4f8; padding: 20px; color: #333; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .post { border-bottom: 1px solid #e1e8ed; padding: 15px 0; }
        .post-header { color: #1da1f2; font-weight: bold; margin-bottom: 5px; }
        .post-time { color: #888; font-size: 0.8em; font-weight: normal; }
        .post-content { background-color: #f8f9fa; padding: 10px; border-radius: 4px; line-height: 1.5; }
        .msg { color: #e0245e; font-weight: bold; text-align: center; }
        input[type="text"], input[type="password"], textarea { width: 100%; padding: 8px; margin: 5px 0 15px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        input[type="submit"] { background-color: #1da1f2; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: bold; width: 100%; }
        input[type="submit"]:hover { background-color: #0c85d0; }
    </style>
</head>
<body>
<div class="container">
    <h2 style="text-align: center; color: #1da1f2;">Call-Sync</h2>
    <?php if (!empty($msg)) echo "<p class='msg'>$msg</p>"; ?>

    <?php if (empty($_SESSION['user_id'])): ?>
        
        <h3 style="border-bottom: 2px solid #eee; padding-bottom: 5px;">ログイン</h3>
        <form action="" method="post">
            社員番号: <input type="text" name="login_emp_no" required>
            パスワード: <input type="password" name="login_password" required>
            <input type="submit" name="login" value="ログイン">
        </form>
        
        <br><br>
        <h3 style="border-bottom: 2px solid #eee; padding-bottom: 5px;">新規オペレーター登録</h3>
        <form action="" method="post">
            社員番号: <input type="text" name="emp_no" required>
            名前: <input type="text" name="name" required>
            パスワード: <input type="password" name="password" required>
            <input type="submit" name="register" value="登録">
        </form>

    <?php else: ?>
        
        <p style="text-align: right;">
            お疲れ様です、<strong><?php echo htmlspecialchars($_SESSION['name'], ENT_QUOTES); ?></strong> さん！ 
            <a href="?action=logout" style="color: #e0245e; text-decoration: none; margin-left: 10px;">[ログアウト]</a>
        </p>

        <form action="" method="post">
            <textarea name="content" rows="4" placeholder="イレギュラー対応やトークTipsを共有しましょう..." required></textarea>
            <input type="submit" name="post_content" value="ナレッジを共有">
        </form>
        <hr style="margin: 20px 0; border: none; border-top: 2px solid #eee;">
        
        <h3>最新のナレッジ</h3>
        <?php
        // 上部で定義した自作関数を使ってデータを取得・表示
        if (isset($pdo)) {
            $posts = getPosts($pdo);
            foreach ($posts as $post) {
                echo "<div class='post'>";
                echo "<div class='post-header'>📞 " . htmlspecialchars($post['name'], ENT_QUOTES) . " <span class='post-time'>(" . $post['created_at'] . ")</span></div>";
                echo "<div class='post-content'>" . nl2br(htmlspecialchars($post['content'], ENT_QUOTES)) . "</div>";
                echo "</div>";
            }
        }
        ?>
        
    <?php endif; ?>
</div>
</body>
</html>