<?php
require_once('U2F_Login.php');

class L extends U2F_Login {
    protected function getUserId() {
        if (isset($_SESSION['user_id']))
            return $_SESSION['user_id'];
        else
            return 0;
    }

    protected function plaintextLogin($username, $password) {
        $sel = $this->pdo->prepare('SELECT user_id FROM user WHERE user=?');
        $sel->execute(array($username)); //FIXME $password
        return $sel->fetchColumn(0);
    }

    protected function login($user_id) {
        $_SESSION['user_id'] = $user_id;
    }
}


$pdo = new PDO('mysql:dbname=u2f', 'test', 'bonjour');
$login = new L($pdo);
if (isset($_GET['force'])) {
    $_SESSION['user_id'] = $_GET['force'];
    header('Location: login.php');
}
$login->route();
?>
<!DOCTYPE html>
<html>
<head>
<title>PHP U2F example</title>
<script src="../assets/u2f-api.js"></script>
<script src="ajax.js"></script>
<script src="login.js"></script>
</head>
<body>

<pre>
<?=print_r($_SESSION)?>
</pre>
<form onsubmit="return login(this)">
    <p><label>User name: <input name="username" required></label></p>
    <!-- FIXME required -->
    <p><label>Password: <input name="password" type="password"></label></p>
    <p>
        <label>Register
            <input value="register" name="action" type="radio" required>
            <input name="comment" placeholder="device name">
        <label>Authenticate:
            <input value="authenticate" name="action" type="radio"></p>
    <p><button type="submit">Submit!</button></p>
</form>

</body>
</html>
