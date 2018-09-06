<?php
//FIXME don't assume PDO::FETCH_OBJ
require_once('../../src/u2flib_server/U2F.php');
session_start();

/**
 * Server-side counterpart of login.js
 */
abstract class U2F_Login {
    /**
     * @return the currently logged user's id
     */
    protected abstract function getUserId();

    /**
     * Performs plaintext authentication
     * @return user_id
     */
    protected abstract function plaintextLogin($username, $password);

    /**
     * Setup a session for the given user id.
     * @remark not in the same context than plaintextLogin()!
     */
    protected abstract function login($user_id);


    protected $pdo;
    protected $u2f;

    public function __construct($pdo) {
        $scheme = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
        $this->u2f = new u2flib_server\U2F($scheme . $_SERVER['HTTP_HOST']);
        $this->pdo = $pdo;
    }

    public function setup() {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS u2f('.
                            'keyHandle VARBINARY(255) PRIMARY KEY, '.
                            'publicKey VARBINARY(255) NOT NULL, '.
                            'counter INTEGER NOT NULL, '.
                            'cmt VARCHAR(25), '.
                            'certificate TEXT, '. //TODO optional?
                            'user_id INTEGER NOT NULL, '.
                            'UNIQUE (user_id, cmt), '.
                            'FOREIGN KEY (user_id) '.
                                'REFERENCES user(user_id) '.
                                'ON DELETE CASCADE)');
    }

    public final function getDevices($user_id) {
        $sel = $this->pdo->prepare('SELECT keyHandle, publicKey, counter FROM u2f WHERE user_id=?');
        $sel->execute(array($user_id));
        return $sel->fetchAll(PDO::FETCH_OBJ);
    }

    private function register($password) {
        $devices = $this->getDevices($this->getUserId());
        list($req, $sigs) = $this->u2f->getRegisterData($devices);
        $_SESSION['u2f_reg'] = $req; //FIXME abstract this, too?
        return array('req' => $req, 'sigs' => $sigs);
    }

    private function register2($cmt, $reg) {
        $req = $_SESSION['u2f_reg']; //set by register()
        //FIXME explicitly test it's been defined?
        //FIXME reset this in other methods!
        $reg = $this->u2f->doRegister($req, $reg);
        $ins = $this->pdo->prepare('INSERT INTO u2f values (?, ?, ?, ?, ?, ?)');
        try {
            $ins->execute(array($reg->keyHandle, $reg->publicKey, $reg->counter, $cmt, $reg->certificate, $this->getUserId()));
            return array('status' => 'success');
        }
        catch (Exception $e) {
            //FIXME detect foreign key constraint violations?
            return array('status' => 'error', 'error' => 'duplicate');
        }
    }

    private function authenticate($username, $password) {
        $requested_user = $this->plaintextLogin($username, $password);
        if (!$requested_user)
            return array('status' => 'error', 'error' => 'invalid user/pass');
        $devices = $this->getDevices($requested_user);
        if (empty($devices)) {
            $this->login($requested_user);
            return array();
        }
        else {
            $_SESSION['u2f_requested'] = $requested_user;
            $_SESSION['u2f_auth'] = $this->u2f->getAuthenticateData($devices);
            return $_SESSION['u2f_auth'];
        }
    }

    private function authenticate2($sig) {
        $requested_user = $_SESSION['u2f_requested']; //set by authenticate()
        $req = $_SESSION['u2f_auth'];
        //FIXME explicitly test they've been defined?
        $reg = $this->u2f->doAuthenticate($req, $this->getDevices($requested_user), $sig);
        $upd = $this->pdo->prepare('UPDATE u2f SET counter=? WHERE keyHandle=?');
        $upd->execute(array($reg->counter, $reg->keyHandle));
        $this->login($requested_user);
        return array('status' => 'success');
    }

    public final function route($allow_reauth=false) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            header('Content-type: application/json');
            $inp = json_decode(file_get_contents('php://input'));
            if ($this->getUserId()) {
                if (isset($_GET['register']))
                    die(json_encode($this->register($inp->password)));
                else if (isset($_GET['register2']))
                    die(json_encode($this->register2($inp->cmt, $inp->reg)));
            }
            if (!$this->getUserId() || $allow_reauth) {
                if (isset($_GET['auth']))
                    die(json_encode($this->authenticate($inp->username, $inp->password)));
                else if (isset($_GET['auth2']))
                    die(json_encode($this->authenticate2($inp)));
            }
            die(json_encode(array('error' => 'invalid command')));
        }
    }
}
