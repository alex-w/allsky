<?php

function DisplayEditor()
{
    global $useLocalWebsite, $useRemoteWebsite;
    global $hasLocalWebsite, $hasRemoteWebsite;
    $myStatus = new StatusMessages();

    $fullN = null;			// this is the file that's displayed by default
    $localN = basename(getLocalWebsiteConfigFile());
    $localN_withComment = "$localN (local Allsky Website)";
    $fullLocalN = "website/$localN";
    $remoteN = basename(getRemoteWebsiteConfigFile());
    $remoteN_withComment = "$remoteN (remote Allsky Website)";
    $fullRemoteN = "config/$remoteN";

    // See what files there are to edit.
    $numFiles = 0;

    if ($hasLocalWebsite) {
        $fullN = $fullLocalN;
        $numFiles++;
        if (!$useLocalWebsite) {
            $msg =  "<span class='WebUISetting'>Use Local Website</span> is not enabled.";
            $msg .= "<br>Your changes won't take effect until you enable that setting.</span>";
            $myStatus->addMessage($msg, 'danger');
        }
    } else {
        $msg =  "<div class='dropdown'><code>$localN_withComment</code>";
		$msg .= " will appear in the list below if you enable";
		$msg .= " <span class='WebUISetting'>Use Local Website</span>.</div>";
        $myStatus->addMessage($msg, 'info');
        $localN = null;
    }

    if ($hasRemoteWebsite) {
        if ($fullN === null)
            $fullN = $fullRemoteN;
        $numFiles++;
        if (!$useRemoteWebsite) {
            $msg = "<span class='WebUISetting'>Use Remote Website</span> is not enabled.";
            $msg .= "<br>Your changes won't take effect until you enable that setting.</span>";
            $myStatus->addMessage($msg, 'danger');
        }
    } else {
        $remoteN = null;
    }

    if (true) {
        $envN = null;	// Don't allow users to edit - they should use the Allsky Settings page.
    } else {
        $envN = basename(ALLSKY_ENV);
        $fullenvN = "current/$envN";
        if (file_exists(ALLSKY_ENV)) {
            if ($fullN === null)
                $fullN = $fullenvN;
            $numFiles++;
        } else {
            $envN = null;
        }
    }

    if ($numFiles > 0) {
        if ($fullN === null) {
            if ($hasLocalWebsite)
                $fullN = $fullLocalN;
            else
                $fullN = $fullRemoteN;
        }
        ?>
        <script type="text/javascript">
            let CONFIG_UPDATE_STRING = "<?php echo CONFIG_UPDATE_STRING ?>"
            $(document).ready(function () {
                let clearTimer = null;
                let currentMarks = [];
                var editor = null;

                function highlightText(searchTerm) {
                    currentMarks.forEach(mark => mark.clear());
                    currentMarks = [];

                    if (!searchTerm) return;

                    let num = 0;
                    const cursor = editor.getSearchCursor(searchTerm, null, { caseFold: true });
                    while (cursor.findNext()) {
                        const mark = editor.markText(cursor.from(), cursor.to(), {
                            className: "highlight",
                        });
                        num++;
                        currentMarks.push(mark);
                    }
                    if (num == 0) {
                        document.getElementById("need-to-update").innerHTML = '';
                    } else {
                        let m = "NOTE: You must update all <span class='cm-string highlight'>" + CONFIG_UPDATE_STRING + "</span>";
                        m += " values before the Website will work.";
                        let msg = '<div class="alert alert-danger" style="font-size: 150%">' + m + '</div>';
                        document.getElementById("need-to-update").innerHTML = msg;
                    }
                }

                function validateJSON(jsonString) {
                    try {
                        JSON.parse(jsonString);
                        return { valid: true, error: null };
                    } catch (e) {
                        const match = e.message.match(/at position (\d+)/);
                        const position = match ? parseInt(match[1], 10) : null;
                        return { valid: false, error: e.message, position: position };
                    }
                }

                function startTimer() {
                    clearTimer = setInterval(() => {
                        clearInterval(clearTimer);
                        clearTimer = null;
                        document.getElementById("editor-messages").innerHTML = '';
                    }, 5000);
                }

                $.get("<?php echo $fullN; ?>", function (data) {

                    // .json files return "data" as json array, and we need a regular string.
                    // Get around this by stringify'ing "data".
                    if (typeof data != 'string') {
                        data = JSON.stringify(data, null, "\t");
                    }

                    editor = CodeMirror(document.querySelector("#editorContainer"), {
                        value: data,
                        theme: "monokai",
                        lineNumbers: true,
                        mode: "application/json",
                        gutters: ["CodeMirror-lint-markers"],
                        lint: true
                    });

                    editor.on("change", (instance, changeObj) => {
                        highlightText(CONFIG_UPDATE_STRING);
                    });

                    highlightText(CONFIG_UPDATE_STRING);
                });

                $("#save_file").click(function () {
                    if (clearTimer !== null) {
                        clearInterval(clearTimer);
                        clearTimer = null;
                    }
                    var content = editor.doc.getValue(); //textarea text
                    let jsonStatus = validateJSON(content);
                    if (jsonStatus.valid) {
                        var path = $("#script_path").val(); //path of the file to save
                        var isRemote = path.substr(0, 8) === "{REMOTE}";
                        if (isRemote)
                            fileName = path.substr(8);
                        else
                            fileName = path;

                        $(".panel-body").LoadingOverlay('show', {
                            background: "rgba(0, 0, 0, 0.5)"
                        });
                        $.ajax({
                            type: "POST",
                            url: "includes/save_file.php",
                            data: { content: content, path: fileName, isRemote: isRemote },
                            dataType: 'text',
                            cache: false,
                            success: function (data) {
                                // "data" is a string with a return code (ERROR or SUCCESS),
                                // then a tab, then a message.
                                var returnMsg = "";
                                var ok = true;
                                var c = "success";		// CSS class
                                if (data == "") {
                                    returnMsg = "No response from save_file.php";
                                    c = "danger";
                                    ok = false;
                                } else {
                                    returnArray = data.split("\n");

                                    // Check every line in the output.
                                    // output any lines not beginnning with "S " or "E ",
                                    // they are probably debug lines.
                                    for (var i = 0; i < returnArray.length; i++) {
                                        var line = returnArray[i];
                                        returnStatus = line.substr(0, 2);
                                        if (returnStatus === "S\t") {
                                            returnMsg += line.substr(2);
                                        } else if (returnStatus === "W\t") {
                                            c = "warning";
                                            returnMsg += line.substr(2);
                                        } else if (returnStatus === "E\t") {
                                            ok = false;
                                            c = "danger";
                                            returnMsg += line.substr(2);
                                        } else {
                                            // Assume it's a debug statement.  Display whole line.
                                            c = "info";
                                            console.log(line);
                                        }
                                    }
                                }
                                var messages = document.getElementById("editor-messages");
                                if (messages === null) {
                                    ok = false;
                                    c = "danger";
                                    returnMsg = "No response from save_file.php";
                                }
                                var m = '<div class="alert alert-' + c + '">' + returnMsg + '</div>';
                                messages.innerHTML = m;
                                $(".panel-body").LoadingOverlay('hide');
                                if (c !== "danger") {
                                    startTimer();
                                }
                            },
                            error: function (XMLHttpRequest, textStatus, errorThrown) {
                                $(".panel-body").LoadingOverlay('hide');
                                alert("Unable to save '" + fileName + ": " + errorThrown);
                                startTimer();
                            }
                        });
                    } else {
                        let message = "<span class='errorMsgBig'>Error:</span>";
                        message += "<br><h3>Unable to save as the configuration file is invalid.</h3>";
                        message += "<br><h4>" + jsonStatus.error + "</h4>";
                        bootbox.alert({
                            message: message,
                            buttons: {
                                ok: {
                                    label: 'OK',
                                    className: 'btn-danger'
                                }
                            }
                        });
                    }
                });

                $("#script_path").change(function (e) {
                    editor.getDoc().setValue("");	// Keeps new file from reading old one first.
                    var fileName = e.currentTarget.value;
                    if (fileName.substr(0, 8) === "{REMOTE}")
                        fileName = fileName.substr(8);
                    var ext = fileName.substring(fileName.lastIndexOf(".") + 1);
                    if (ext == "js") {
                        editor.setOption("mode", "javascript");
                    } else if (ext == "json") {
                        editor.setOption("mode", "json");
                    } else {
                        editor.setOption("mode", "shell");
                    }
                    // It would be easy to support other files types.
                    // Would need "type.js" file to do the formatting.
                    $.get(fileName + "?_ts=" + new Date().getTime(), function (data) {
                        data = JSON.stringify(data, null, "\t");
                        editor.getDoc().setValue(data);
                        highlightText(CONFIG_UPDATE_STRING);
                    }).fail(function (x) {
                        if (x.status == 200) {	// json files can fail but actually work
                            editor.getDoc().setValue(x.responseText);
                        } else {
                            alert('Requested file [' + fileName + '] not found or an unsupported language.');
                        }
                    })
                });
            });

        </script>
    <?php } ?>

    <div class="row">
        <div class="col-lg-12">
            <div class="panel panel-primary">
                <div class="panel-heading"><i class="fa fa-code fa-fw"></i> Editor</div>
                <div class="panel-body">
                    <p id="need-to-update"></p>
                    <p id="editor-messages"><?php $myStatus->showMessages(); ?></p>
                    <div id="editorContainer"></div>
                    <div class="editorBottomSection">
                        <?php
                        if ($numFiles === 0) {
                            ?>
                                <div id="as-editor-overlay" class="as-overlay big">
                                    <div class="center-full">
                                        <div class="center-paragraph">
                                            <h1>There are no files to edit</h1>
                                            <p>No configuration files could be found to edit.</p>
                                        </div>
                                    </div>
                                </div> 
                            <?php

                        } else {
                            ?>
                            <select class="form-control editorForm" id="script_path" title="Pick a file">
                                <?php
                                if ($localN !== null) {
                                    // The website is installed on this Pi.
                                    // The physical path is ALLSKY_WEBSITE; virtual path is "website".
                                    echo "<option value='$fullLocalN'>";
                                    echo $localN_withComment;
                                    echo "</option>";
                                }

                                if ($remoteN !== null) {
                                    // A copy of the remote website's config file is on the Pi.
                                    echo "<option value='{REMOTE}$fullRemoteN'>";
                                    echo $remoteN_withComment;
                                    echo "</option>";
                                }

                                if ($envN !== null) {
                                    echo "<option value='$fullenvN'>";
                                    echo "$envN";
                                    echo "</option>";
                                }
                                ?>
                            </select>
                            <button type="submit" class="btn btn-primary editorSaveChanges" id="save_file">
                                <i class="fa fa-save"></i> Save Changes</button>
                            <?php
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}
?>
