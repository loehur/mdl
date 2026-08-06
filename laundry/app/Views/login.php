<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <link rel="icon" href="<?= URL::IN_ASSETS ?>icon/j-icon.svg" type="image/svg+xml">
    <?php require_once __DIR__ . '/pwa_head.php'; ?>
    <title>MDL Laundry — Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
    <link href="<?= URL::EX_ASSETS ?>plugins/fontawesome-free-5.15.4-web/css/all.css" rel="stylesheet">
    <style>
        @font-face {
            font-family: "fontku";
            src: url("<?= URL::EX_ASSETS ?>font/Titillium-Regular.otf");
        }

        /* MDL UI Theme — lihat laundry/docs/UI_THEME.md */
        :root {
            --ink: #0f172a;
            --ink-soft: #1e293b;
            --line: #94a3b8;
            --line-soft: #cbd5e1;
            --blue: #2563eb;
            --blue-deep: #1d4ed8;
            --green: #16a34a;
            --green-deep: #15803d;
            --yellow: #f59e0b;
            --yellow-deep: #d97706;
            --red: #dc2626;
            --red-deep: #b91c1c;
            --surface: #ffffff;
            --radius: 0;
            --border: 1px;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            min-height: 100%;
            font-family: 'fontku', 'Segoe UI', sans-serif;
            color: var(--ink);
            background: #eef4ff;
        }

        body.login-page {
            min-height: 100vh;
            display: flex;
            align-items: stretch;
            justify-content: center;
            overflow-x: hidden;
        }

        .login-scene {
            position: relative;
            width: 100%;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 28px 16px 40px;
            background:
                radial-gradient(90% 60% at 0% 0%, rgba(37,99,235,.18), transparent 50%),
                radial-gradient(80% 50% at 100% 0%, rgba(245,158,11,.16), transparent 45%),
                radial-gradient(70% 45% at 80% 100%, rgba(22,163,74,.14), transparent 50%),
                linear-gradient(180deg, #eef4ff 0%, #f4fff8 50%, #fff8eb 100%);
        }

        .login-scene::before {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
            background-image:
                repeating-linear-gradient(
                    105deg,
                    transparent 0,
                    transparent 46px,
                    rgba(15,23,42,.03) 46px,
                    rgba(15,23,42,.03) 48px
                );
            opacity: .8;
        }

        .login-steam {
            position: absolute;
            inset: auto 0 0 0;
            height: 40%;
            pointer-events: none;
            background:
                radial-gradient(ellipse 40% 60% at 20% 100%, rgba(37,99,235,.12) 0%, transparent 70%),
                radial-gradient(ellipse 35% 55% at 55% 100%, rgba(22,163,74,.12) 0%, transparent 65%),
                radial-gradient(ellipse 40% 50% at 85% 100%, rgba(245,158,11,.14) 0%, transparent 70%);
            animation: steam-rise 8s ease-in-out infinite alternate;
        }

        .login-bubbles {
            position: absolute;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
        }
        .login-bubbles span {
            position: absolute;
            bottom: -20px;
            width: 10px;
            height: 10px;
            border-radius: var(--radius);
            border: var(--border) solid rgba(37, 99, 235, .35);
            background: rgba(255,255,255,.35);
            animation: bubble-up linear infinite;
        }
        .login-bubbles span:nth-child(1) { left: 12%; width: 8px; height: 8px; animation-duration: 11s; animation-delay: 0s; border-color: rgba(37,99,235,.4); }
        .login-bubbles span:nth-child(2) { left: 28%; width: 14px; height: 14px; animation-duration: 14s; animation-delay: 2s; border-color: rgba(22,163,74,.4); }
        .login-bubbles span:nth-child(3) { left: 48%; width: 9px; height: 9px; animation-duration: 10s; animation-delay: 4s; border-color: rgba(245,158,11,.45); }
        .login-bubbles span:nth-child(4) { left: 67%; width: 12px; height: 12px; animation-duration: 13s; animation-delay: 1s; border-color: rgba(37,99,235,.35); }
        .login-bubbles span:nth-child(5) { left: 84%; width: 7px; height: 7px; animation-duration: 12s; animation-delay: 3.5s; border-color: rgba(22,163,74,.4); }

        @keyframes steam-rise {
            from { opacity: .75; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(-6px); }
        }
        @keyframes bubble-up {
            0% { transform: translateY(0) scale(1); opacity: 0; }
            15% { opacity: .7; }
            100% { transform: translateY(-85vh) scale(1.15); opacity: 0; }
        }
        @keyframes rise-in {
            from { opacity: 0; transform: translateY(18px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-shell {
            position: relative;
            z-index: 2;
            width: min(420px, 100%);
            animation: rise-in .55s ease-out both;
        }

        .login-brand {
            text-align: center;
            margin: 0 0 22px;
            animation: rise-in .65s ease-out .05s both;
        }
        .login-brand__name {
            margin: 0;
            font-family: 'fontku', 'Segoe UI', sans-serif;
            font-size: clamp(2.4rem, 9vw, 3.1rem);
            font-weight: 900;
            letter-spacing: -0.04em;
            line-height: .95;
            color: var(--ink);
            text-shadow: 0 1px 0 rgba(255,255,255,.5);
        }
        .login-brand__name em {
            font-style: normal;
            background: linear-gradient(105deg, var(--blue-deep) 0%, var(--blue) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .login-brand__tag {
            margin: 8px 0 0;
            font-size: 13px;
            font-weight: 900;
            color: var(--ink-soft);
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .login-panel {
            background: var(--surface);
            border: var(--border) solid #93c5fd;
            border-radius: var(--radius);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.1);
            padding: 0;
            overflow: hidden;
            animation: rise-in .7s ease-out .12s both;
        }
        .login-panel__head {
            padding: 12px 16px;
            background: linear-gradient(105deg, #1d4ed8 0%, #2563eb 100%);
            color: #fff;
            font-size: 0.95rem;
            font-weight: 900;
            letter-spacing: -0.02em;
            text-shadow: 0 1px 0 rgba(0,0,0,.18);
        }
        .login-panel__body {
            padding: 18px 16px 16px;
            background:
                radial-gradient(90% 60% at 0% 0%, rgba(37,99,235,.08), transparent 50%),
                radial-gradient(80% 50% at 100% 0%, rgba(245,158,11,.08), transparent 45%),
                linear-gradient(180deg, #eff6ff 0%, #fff 55%);
        }

        .login-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: 100%;
            min-width: 0;
        }

        .login-lead {
            margin: 0 0 14px;
            font-size: 0.78rem;
            font-weight: 900;
            color: var(--ink-soft);
            text-align: center;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .login-freq {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
            margin: 0 0 16px;
        }
        .login-freq__btn {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 34px;
            padding: 6px 4px;
            border: var(--border) solid var(--line);
            border-radius: var(--radius);
            background: #fff;
            color: var(--ink);
            font-family: inherit;
            font-size: 12px;
            font-weight: 900;
            cursor: pointer;
            transition: border-color .15s, background .15s, color .15s;
        }
        .login-freq__btn:hover {
            border-color: var(--blue);
            background: #eff6ff;
            color: var(--blue-deep);
        }

        #info {
            margin: 0 0 12px;
        }
        #info:empty { display: none; margin: 0; }
        .login-alert {
            padding: 10px 12px;
            font-size: 13px;
            font-weight: 800;
            line-height: 1.35;
            border: var(--border) solid transparent;
            border-radius: var(--radius);
        }
        .login-alert--err {
            background: linear-gradient(180deg, #fef2f2, #fff);
            border-color: #fca5a5;
            color: var(--red-deep);
        }
        .login-alert--ok {
            background: linear-gradient(180deg, #f0fdf4, #fff);
            border-color: #86efac;
            color: var(--green-deep);
        }

        .login-field {
            display: flex;
            align-items: stretch;
            width: 100%;
            min-width: 0;
            max-width: 100%;
            border: var(--border) solid var(--line);
            border-radius: var(--radius);
            background: #fff;
            transition: border-color .15s, box-shadow .15s;
            overflow: hidden;
        }
        .login-field:focus-within {
            border-color: var(--blue);
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22);
        }
        .login-field input {
            flex: 1 1 auto;
            min-width: 0;
            width: 100%;
            height: 44px;
            padding: 0 12px;
            border: 0;
            border-radius: var(--radius);
            outline: none;
            background: transparent;
            font-family: inherit;
            font-size: 15px;
            font-weight: 800;
            color: var(--ink);
        }
        .login-field input::placeholder {
            color: #94a3b8;
            font-weight: 700;
        }
        .login-field__action,
        .login-field__icon {
            flex: 0 0 40px;
            width: 40px;
            display: grid;
            place-items: center;
            border: 0;
            border-left: var(--border) solid var(--line-soft);
            border-radius: var(--radius);
            background: #eff6ff;
            color: var(--ink-soft);
            cursor: default;
        }
        .login-field__action {
            cursor: pointer;
            color: var(--blue);
            transition: background .15s, color .15s;
        }
        .login-field__action:hover {
            background: var(--blue);
            color: #fff;
        }
        .login-field__captcha {
            flex: 0 0 52px;
            width: 52px;
            max-width: 52px;
            display: grid;
            place-items: center;
            border-left: var(--border) solid var(--line-soft);
            background: #fffbeb;
            padding: 0 2px;
            cursor: pointer;
            overflow: hidden;
        }
        .login-field__captcha img {
            display: block;
            max-height: 28px;
            max-width: 48px;
            width: auto;
            height: auto;
        }

        .login-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            gap: 10px;
            width: 100%;
            min-width: 0;
        }
        .login-row > .login-field {
            min-width: 0;
            max-width: 100%;
        }

        .login-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 4px;
        }

        #spinner {
            display: none;
            width: 22px;
            height: 22px;
            border: 2px solid var(--line-soft);
            border-top-color: var(--blue);
            border-radius: 50%;
            animation: spin .7s linear infinite;
            flex: 0 0 auto;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .login-submit {
            min-width: 128px;
            height: 44px;
            padding: 0 22px;
            border: 0;
            border-radius: var(--radius);
            background: linear-gradient(180deg, var(--green-deep), var(--green));
            color: #fff;
            font-family: 'fontku', 'Segoe UI', sans-serif;
            font-size: 0.95rem;
            font-weight: 900;
            letter-spacing: 0.02em;
            cursor: pointer;
            box-shadow: 0 8px 18px rgba(22, 163, 74, 0.3);
            transition: filter .15s, transform .12s;
        }
        .login-submit:hover {
            filter: brightness(1.05);
            transform: translateY(-1px);
        }
        .login-submit:active {
            transform: translateY(0);
        }

        .login-foot {
            margin: 16px 0 0;
            text-align: center;
            font-size: 11px;
            font-weight: 900;
            color: var(--ink-soft);
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .login-foot span {
            color: var(--blue);
        }
        .login-foot span:nth-child(2) { color: var(--green); }
        .login-foot span:nth-child(3) { color: var(--yellow-deep); }

        @media (max-width: 420px) {
            .login-scene { padding: 20px 12px 28px; }
            .login-panel__body { padding: 16px 14px 14px; }
            .login-row { grid-template-columns: 1fr; }
        }

        @media (prefers-reduced-motion: reduce) {
            .login-steam,
            .login-bubbles span,
            .login-shell,
            .login-brand,
            .login-panel { animation: none !important; }
        }
    </style>
</head>

<body class="login-page">
    <div class="login-scene">
        <div class="login-steam" aria-hidden="true"></div>
        <div class="login-bubbles" aria-hidden="true">
            <span></span><span></span><span></span><span></span><span></span>
        </div>

        <div class="login-shell">
            <header class="login-brand">
                <h1 class="login-brand__name">MDL <em>Laundry</em></h1>
                <p class="login-brand__tag">Masuk ke kasir outlet</p>
            </header>

            <div class="login-panel">
                <div class="login-panel__head">Login</div>
                <div class="login-panel__body">
                    <?php if (count($data) > 0) { ?>
                        <p class="login-lead">Pilih nomor yang sering dipakai</p>
                        <div class="login-freq">
                            <?php
                            if (is_array($data)) {
                                krsort($data, 1);
                            }
                            foreach ($data as $ntm) { ?>
                                <button type="button" class="login-freq__btn freq_number"><?= htmlspecialchars((string) $ntm) ?></button>
                            <?php } ?>
                        </div>
                        <p class="login-lead" style="margin-top:8px">PIN via request WA, atau Access Key</p>
                    <?php } else { ?>
                        <p class="login-lead">Login dengan nomor WhatsApp. PIN via request, atau Access Key.</p>
                    <?php } ?>

                    <div id="info"></div>

                    <form class="login-form" action="<?= URL::BASE_URL ?>Login/cek_login" method="post">
                        <div class="login-field">
                            <input id="hp" type="text" name="username" autocomplete="username" placeholder="Nomor WhatsApp" required
                                inputmode="numeric" pattern="[0-9]*" maxlength="15">
                            <button type="button" class="login-field__action" id="req_pin" title="Minta PIN via WA">
                                <i class="fas fa-mobile-alt"></i>
                            </button>
                        </div>

                        <div class="login-field">
                            <input type="password" name="pin" id="pin" placeholder="PIN / Access Key" required
                                inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code"
                                maxlength="4">
                            <span class="login-field__icon"><i class="fas fa-lock"></i></span>
                        </div>

                        <div class="login-row">
                            <div class="login-field">
                                <input type="text" name="outlet" id="outlet" placeholder="ID Outlet" autocomplete="off"
                                    inputmode="numeric" pattern="[0-9]*" maxlength="12">
                                <span class="login-field__icon"><i class="fas fa-store-alt"></i></span>
                            </div>
                            <div class="login-field">
                                <input type="text" name="cap" id="cap" placeholder="Captcha" required autocomplete="off"
                                    inputmode="numeric" pattern="[0-9]*" maxlength="6">
                                <span class="login-field__captcha" id="captchaWrap" title="Klik untuk refresh">
                                    <img id="captcha" src="<?= URL::BASE_URL ?>Login/captcha" alt="captcha">
                                </span>
                            </div>
                        </div>

                        <div class="login-actions">
                            <div id="spinner" role="status" aria-label="Memuat"></div>
                            <button type="submit" class="login-submit">Masuk</button>
                        </div>
                    </form>
                </div>
            </div>

            <p class="login-foot"><span>Fresh</span> · <span>Clean</span> · <span>Ready</span></p>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        function showInfo(msg, ok) {
            var cls = ok ? 'login-alert login-alert--ok' : 'login-alert login-alert--err';
            $("#info").hide().html('<div class="' + cls + '" role="alert">' + msg + '</div>').fadeIn();
        }

        function refreshCaptcha() {
            $("#captcha").attr('src', '<?= URL::BASE_URL ?>Login/captcha?' + Date.now());
        }

        $("#captchaWrap").on("click", function() {
            refreshCaptcha();
        });

        $("form").on("submit", function(e) {
            $("#spinner").show();
            e.preventDefault();
            $.ajax({
                url: $(this).attr('action'),
                data: $(this).serialize(),
                type: $(this).attr("method"),
                success: function(res) {
                    try {
                        var data = JSON.parse(res);
                        if (data.code == 0) {
                            showInfo(data.msg, false);
                            $("#spinner").hide();
                        } else if (data.code == 1) {
                            showInfo(data.msg, true);
                            $("#spinner").hide();
                        } else if (data.code == 11) {
                            location.reload(true);
                        } else if (data.code == 10) {
                            refreshCaptcha();
                            showInfo(data.msg, false);
                            $("#spinner").hide();
                        }
                    } catch (err) {
                        showInfo(res, false);
                        $("#spinner").hide();
                    }
                },
            });
        });

        $("#req_pin").on("click", function(e) {
            var hp_input = $('#hp').val();
            $("#spinner").show();
            e.preventDefault();
            $.ajax({
                url: '<?= URL::BASE_URL ?>Login/req_pin',
                data: { hp: hp_input },
                type: 'POST',
                success: function(res) {
                    try {
                        var data = JSON.parse(res);
                        if (data.code == 0) {
                            showInfo(data.msg, false);
                            $("#spinner").hide();
                        } else if (data.code == 1) {
                            showInfo(data.msg, true);
                            $("#spinner").hide();
                        } else if (data.code == 11) {
                            location.reload(true);
                        } else if (data.code == 10) {
                            refreshCaptcha();
                            showInfo(data.msg, false);
                            $("#spinner").hide();
                        }
                    } catch (err) {
                        showInfo(res, false);
                        $("#spinner").hide();
                    }
                },
            });
        });

        $(".freq_number").on("click", function() {
            $("input#hp").val($(this).text().trim().replace(/\D/g, ''));
        });

        $("#hp, #pin, #outlet, #cap").on("input", function() {
            this.value = this.value.replace(/\D/g, '');
        });
    });
    </script>
<?php require_once __DIR__ . '/pwa_register.php'; ?>
</body>

</html>
