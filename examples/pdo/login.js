function register(password, cmt) {
    return post("login.php?register", {password}).then(function (data) {
        return new Promise(function (resolve, reject) {
            u2f.register(data.req.appId, [data.req], data.sigs, function f(reg) {
                if (reg.errorCode)
                    reject({u2f_error: reg.errorCode});
                else
                    resolve({reg, cmt});
            });
        });
    }).then(function (data) {
        return post("login.php?register2", data);
    });
}

function authenticate(username, password) {
    return post("login.php?auth", {username, password}).then(function (devs) {
        if (devs.length)
            return new Promise(function (resolve, reject) {
                u2f.sign(devs[0].appId, devs[0].challenge, devs, function f(sig) {
                    if (sig.errorCode)
                        reject({u2f_error: sig.errorCode});
                    else
                        resolve(sig);
                });
            }).then(function (data) {
                return post("login.php?auth2", data);
            });
        else if (!devs.error)
            return Promise.resolve({status: "success"});
    });
}


function login(f) {
    switch (f.action.value) {
        case 'register':
            register(f.password.value, f.comment.value).then(console.log);
            break;
        case 'authenticate':
            authenticate(f.username.value, f.password.value).then(console.log);
            break;
    }
    /*
    .catch(function (e) {
        console.error(e);
    });
    */
    return false;
}
