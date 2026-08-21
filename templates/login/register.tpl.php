<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Register</title>
        <meta name="title" content="Register"/>
        <meta name="description" content=""/>
        <link rel="stylesheet" href="/css/styles.css">
    </head>
    <body><!-- Register form area -->
        <div class="PageWrapper">
            <div class="PagePanel">
                <div class="head"><h5 class="iUser">Register</h5></div>
                <form action="" id="valid" class="mainForm" method="POST">
                    <?= $csrf_field ?? '' ?>
                    <fieldset>
                        <div class="PageRow noborder">
                            <label for="req1">Username:</label>
                            <div class="PageInput"><input type="text" name="username" class="validate[required]" id="req1" /></div>
                            <div class="fix"></div>
                        </div>

                        <div class="PageRow noborder">
                            <label for="req2">Password:</label>
                            <div class="PageInput"><input type="password" name="pass" class="validate[required]" id="req2" /></div>
                            <div class="fix"></div>
                        </div>

                        <div class="PageRow noborder">
                            <label for="req3">Confirm:</label>
                            <div class="PageInput"><input type="password" name="pass_verify" class="validate[required]" id="req3" /></div>
                            <div class="fix"></div>
                        </div>
                        <?php if (!empty($creating_admin_user)): ?>
                        <div class="PageRow noborder">
                            <label for="req4">Setup token:</label>
                            <div class="PageInput"><input type="text" name="setup_token" class="validate[required]" id="req4" autocomplete="off" /></div>
                            <div class="fix"></div>
                        </div>
                        <?php if (!empty($bootstrap_token_missing)): ?>
                        <p class="RegisterSetupHelp">This site has no setup token, so registration is closed. The site owner must deploy one. See the project README.</p>
                        <?php else: ?>
                        <p class="RegisterSetupHelp">First-run admin creation is protected. Paste the setup token you generated when you deployed this site.</p>
                        <?php endif; ?>
                        <?php endif; ?>
                        <div class="PageRow noborder">
                            <input type="submit" value="Register" class="greyishBtn submitForm" />
                            <div class="fix"></div>
                        </div>
                    </fieldset>
                </form>
            </div>
        </div>
        <div class="fix"></div>
    </body>
</html>
