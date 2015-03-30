<?php
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate"); // HTTP/1.1
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache"); // HTTP/1.0
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

include('Roomba.php');
$start = microtime(true);


$roomba = new Roomba('http://192.168.0.28/');
$mix = microtime(true);
//$roomba = new Roomba('http://127.0.0.1:4444');


function urlCommand($name)
{
    return $_SERVER['PHP_SELF'] . '?fun=' . $name;
}

function ajaxCommand($name)
{
    return 'javascript:ajaxBasicFun(\'' . urlCommand($name) . '\');';
}


function ajaxDrive($velocity, $radius)
{
    $url = urlCommand('drive') . '&velocity=' . $velocity . '&radius=' . $radius;
    return 'javascript:ajaxBasicFun(\'' . $url . '\');';
}

function ajaxMotor($on)
{
    $url = urlCommand('motor');
    if ($on) {
        $url .= '&main_brush=true&vacum=true&side_brush=true';
    }
    return 'javascript:ajaxBasicFun(\'' . $url . '\');';
}
/**
 * @param $sensors
 */
function dumpSensors($sensors)
{
    print(' <ul>');
    foreach ($sensors as $name => $value) {
        print(' <li>' . $name . ':');
        if (is_array($value)) {
            dumpSensors($value);
        } else if (is_bool($value)) {
            print($value ? 'on' : 'off');
        } else {
            print($value);
        }
        print(' </li> ');
    }
    print('</ul> ');
}

if (isset($_GET['fun'])) {
    switch ($_GET['fun']) {
        case 'Off':
            $roomba->StopOI();
            break;
        case 'Passive':
            $roomba->StartOI();
            break;
        case 'Safe':
            $roomba->Safe();
            break;
        case 'Full':
            $roomba->Full();
            break;
        case 'clean':
            $roomba->Clean();
            return;
            break;
        case 'spot':
            $roomba->Spot();
            return;
            break;
        case 'max':
            $roomba->Max();
            return;
            break;
        case 'dock':
            $roomba->Dock();
            return;
            break;
        case 'poweroff':
            $roomba->Power();
            break;
        case 'settime':
            $now = time();
            $day = (int)date('w', $now);
            $hour = date('H', $now);
            $minute = date('i', $now);
            $roomba->SetTime($day, $hour, $minute);
            return;
            break;
        case "drive":
            $velocity = $_GET['velocity'];
            $radius = $_GET['radius'];
            $roomba->Drive($velocity, $radius);
            $stop = microtime(true);
            printf("Drive($velocity,$radius)  took %f /%f", ($stop - $start), ($mix - $start));
            print_r($roomba->get_debuglog());
            return;
            break;
        case "motor":
            $main_brush = $_GET['main_brush'] == "true";
            $vacum = $_GET['vacum'] == "true";
            $side_brush = $_GET['side_brush'] == "true";
            $roomba->Motors($main_brush, $vacum, $side_brush);
            return;
            break;
            break;
        case "horn":
            $notes = array(array('note' => 62, "len" => 32));
            $roomba->Song(1, $notes);
            $roomba->Play(1);
            return;
            break;
        case "ledascii":
            $msg = $_GET['msg'];
            $roomba->LedASCII($msg);
            return;
            break;

        case "sensor":
            $sensors = $roomba->getSensorGroup6();
            dumpSensors($sensors);
            return;
    }
}
$r_mode = $roomba->getOIMode();
?>

    <!DOCTYPE html>
    <html>
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1,maximum-scale=1,user-scalable=no"
        /
        <meta http-equiv="Content-Type" content="text/html; charset=windows-1252">
        <title>Test Rommba Interface</title>
        <script language='javascript1.5' type='text/JavaScript'>

            var periodicCall;
            function showHide(str_divid) {
                var div = document.getElementById(str_divid);
                if (div.style.display != 'block') {
                    div.style.visibility = 'visible'
                    div.style.display = 'block';
                    getsensor()
                } else {
                    div.style.display = 'none';
                    div.style.visibility = 'hidden';
                    clearTimeout(periodicCall);
                }
            }


            function getsensor()
            {
                var msg = document.getElementById('msg').value;
                ajax("<?php echo $_SERVER['PHP_SELF']?>?fun=sensor", function (result) {
                    var div = document.getElementById('sensordiv');
                    div.innerHTML = result;
                    if(div.style.visibility == 'visible') {
                        periodicCall = setTimeout(getsensor(), 1000);
                    }

                });
            }

            function ajax(request, callback) {
                if (window.XMLHttpRequest) {
                    // code for IE7+, Firefox, Chrome, Opera, Safari
                    xmlhttp = new XMLHttpRequest();
                } else {
                    // code for IE6, IE5
                    xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
                }
                xmlhttp.onreadystatechange = function () {
                    if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
                        callback(xmlhttp.responseText);
                    }
                };
                xmlhttp.open("GET", request, true);
                xmlhttp.send();

            }


            function ajaxBasicFun(action) {
                ajax(action, function (result) {

                });
            }

            function send_setMsg() {
                var msg = document.getElementById('msg').value;
                ajaxBasicFun("<?php echo $_SERVER['PHP_SELF']?>?fun=ledascii&msg=" + msg);
            }


        </script>


        <style>
            td {
                text-align: center;
            }

            td.left {
                text-align: right;
            }

            td.right {
                text-align: left;
            }

            A.button {
                border-style: solid;
                border-width: 1px 1px 1px 1px;
                margin-left: 5px;
                margin-right: 5px;

                line-height: 32px;
                background-color: #f0f0f0;
                text-decoration: none;
                padding: 4px;
                border-color: gray;
                color: #404040;
                border-radius: 5px;
                -moz-border-radius: 5px;
                -webkit-border-top-left-radius: 5px;
                -webkit-border-top-right-radius: 5px;
                -webkit-border-bottom-right-radius: 5px;
                -webkit-border-bottom-left-radius: 5px;

                background-image: url('button_shadow.png');
                background-repeat: repeat-x;
                background-position: left bottom;
            }

            A.toggle {
                margin-left: 5px;
                margin-right: 5px;
                font-size: x-large;
                font-weight: bold;
                line-height: 32px;
                text-decoration: none;
                padding: 4px;
                border-color: gray;
                color: #404040;
                border-radius: 5px;
                -moz-border-radius: 5px;
                display: block;
            }

            SPAN.button {
                border-style: solid;
                border-width: 2px 2px 2px 2px;
                margin-left: 5px;
                margin-right: 5px;

                line-height: 32px;
                font-weight: bold;
                background-color: #E8E8E8;
                text-decoration: green;
                padding: 4px;
                border-color: gray;
                color: black;
                border-radius: 5px;
                -moz-border-radius: 5px;
                -webkit-border-top-left-radius: 5px;
                -webkit-border-top-right-radius: 5px;
                -webkit-border-bottom-right-radius: 5px;
                -webkit-border-bottom-left-radius: 5px;

                background-image: url('button_shadow.png');
                background-repeat: repeat-x;
                background-position: left bottom;
            }

            table {
                width: 100%;
            }

            A:visited.button {
                background-color: #f0f0f0;
                text-decoration: none;
                color: #404040;
            }

            A:hover.button {
                background-color: #E8E8E8;
                text-decoration: none;
                color: #404040;
            }

            A:active.button {
                background-color: #808080;
                text-decoration: none;
                color: black;
            }

            #sensordiv {
                visibility: hidden;
                display: none;
            }

            div {
                display: block;
                margin-left: 15px;
                margin-right: 5px;

            }
        </style>
    </head>

<body>

    <a class="toggle" href="javascript:showHide('mode');">Mode:</a>

    <div id="mode">
        <?php


        print('<table><tr>');
        foreach ($roomba->modes as $mode_name) {
            if ($mode_name == $r_mode) {
                print('<td><span class="button">' . $mode_name . '</span></td>');
            } else {
                print('<td><a class="button" href="' . urlCommand($mode_name) . '">' . $mode_name . '</a></td>');
            }
        }
        print('</tr></table>');
        ?>
    </div>
    <a class="toggle" href="javascript:showHide('basic');">basic:</a>

    <div id="basic">
        <table>
            <tr>
                <td><a class="button" href="<?php echo ajaxCommand("clean"); ?>">Clean</a></td>
                <td><a class="button" href="<?php echo ajaxCommand("spot"); ?>">Spot</a></td>
                <td><a class="button" href="<?php echo ajaxCommand("dock"); ?>">Dock</a></td>
            </tr>
        </table>
    </div>
    <a class="toggle" href="javascript:showHide('advanced');">Advanced command:</a>

    <div id="advanced">

    <?php

    if ($r_mode == "Safe" || $r_mode == "Full") {

        print("<table><tr>");
        print('<td class="left"><a class="button" href="' . ajaxDrive(150, 200) . '">front left</a></td>');
        print('<td><a class="button" href="' . ajaxDrive(200, 0) . '">front</a></td>');
        print('<td class="right"><a class="button" href="' . ajaxDrive(150, -200) . '">front right</a></td>');

        print("</tr><tr>");

        print('<td class="left"><a class="button" href="' . ajaxDrive(100, 1) . '">left</a></td>');
        print('<td><a class="button" href="' . ajaxDrive(0, 0) . '">stop</a></td>');
        print('<td class="right"><a class="button" href="' . ajaxDrive(100, -1) . '">right</a></td>');
        print("</tr><tr>");

        print('<td class="left"><a class="button" href="' . ajaxMotor(true) .'"> Brush ON </a ></td > ');
        print('<td ><a class="button" href = "' . ajaxCommand('horn') . '" > horn</a ></td > ');
        print('<td class="right" ><a class="button" href = "' .  ajaxMotor(false) . '" > Brush OFF </a ></td > ');

        print("</tr></table>");


        print("<br /><br /><table><tr>");
        print('<td class="left"><input type = "text" size = "4" maxlength = "4" name = "msg" id = "msg" /></td>');
        print('<td><a class="button" href = "javascript:send_setMsg();" > Show text </a ></td>');
        print("</tr></table>");


    } else {
        print("switch to Safe or Full mode to control the Roomba");
    }
    ?>
</div>
<a class="toggle" href="javascript:showHide('sensordiv');">Sensors:</a>

<div id="sensordiv">
    <?php

    ?>
</div>

<?php
if (false) {

    print('<a class="toggle" href = "javascript:showHide(\'debuglogs\');" > Debug logs:</a > ');
    print('<div id = "debuglogs" > ');
    $logs = $roomba->get_debuglog();
    print("<ol>\n");
    foreach ($logs as $line) {
        print("<li>$line</li>\n");
    }
    print("</ol></div>\n");
}


?>
</body>
</html>
