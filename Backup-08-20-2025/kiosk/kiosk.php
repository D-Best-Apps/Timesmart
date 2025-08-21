<!DOCTYPE html>
<html>
<head>
    <title>Kiosk Mode</title>
    <link rel="stylesheet" href="../css/kiosk.css">
    <link rel="icon" type="image/webp" href="../images/D-Best-favicon.webp">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
<div class="wrapper">
    <div class="main">
        <h2>Kiosk Mode</h2>
        <div id="message"></div>
        <form id="kioskForm">
            <div class="form-group">
                <label for="tag_id">Scan Your Tag</label>
                <input type="text" id="tag_id" name="tag_id" required autofocus>
            </div>
            <button type="button" id="proceedBtn" class="btn-kiosk">Proceed</button>
            <div id="userName"></div>
            <div class="kiosk-actions" id="kioskActions" style="display: none;">
                <button type="submit" name="action" value="in" class="btn-kiosk btn-in">Clock In</button>
                <button type="submit" name="action" value="out" class="btn-kiosk btn-out">Clock Out</button>
                <button type="submit" name="action" value="lunch_start" class="btn-kiosk btn-lunch">Start Lunch</button>
                <button type="submit" name="action" value="lunch_end" class="btn-kiosk btn-lunch-end">End Lunch</button>
            </div>
        </form>
    </div>
</div>

<script src="../js/kiosk.js"></script>
<script src="../js/kiosk_proceed.js"></script>

</body>
</html>
