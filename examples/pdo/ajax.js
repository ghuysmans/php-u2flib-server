function post(url, json) {
    return new Promise(function (resolve, reject) {
        var xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function(e) {
            if (this.readyState === XMLHttpRequest.DONE)
                if (this.status === 200)
                    try {
                        resolve(JSON.parse(this.responseText));
                    }
                    catch (e) {
                        reject(e);
                    }
                else
                    reject({server_error: this.status});
        };
        xhr.open("POST", url, true);
        xhr.setRequestHeader("Content-Type", "application/json");
        xhr.send(JSON.stringify(json));
    });
}
