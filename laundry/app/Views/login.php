<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <link rel="icon" href="<?= URL::EX_ASSETS ?>icon/logo.png">
    <?php require_once __DIR__ . '/pwa_head.php'; ?>
    <title>MDL Laundry — Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
    <link href="<?= URL::EX_ASSETS ?>plugins/fontawesome-free-5.15.4-web/css/all.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;700;800&display=swap" rel="stylesheet">
    <style>
        @font-face {
            font-family: "fontku";
            src: url("<?= URL::EX_ASSETS ?>font/Titillium-Regular.otf");
        }

        :root {
            --ink: #1a2740;
            --ink-soft: #5a6a82;
            --line: #c5d3e4;
            --accent: #2f61bc;
            --accent-deep: #214a96;
            --fresh: #3db8a8;
            --fresh-deep: #2a9a8c;
            --foam: #e8f4f8;
            --panel: rgba(255, 255, 255, 0.92);
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            min-height: 100%;
            font-family: 'fontku', 'Outfit', sans-serif;
            color: var(--ink);
            background: #9eb8d4;
        }

        body.login-page {
            min-height: 100vh;
            display: flex;
            align-items: stretch;
            justify-content: center;
            overflow-x: hidden;
        }

        /* —— Atmosphere: laundry wash / steam —— */
        .login-scene {
            position: relative;
            width: 100%;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 28px 16px 40px;
            background:
                radial-gradient(ellipse 90% 60% at 15% 10%, rgba(255,255,255,.55) 0%, transparent 55%),
                radial-gradient(ellipse 70% 50% at 90% 85%, rgba(61,184,168,.28) 0%, transparent 50%),
                linear-gradient(165deg, #7ea4cc 0%, #a8c5de 38%, #c5dceb 72%, #dceaf2 100%);
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
                    rgba(255,255,255,.045) 46px,
                    rgba(255,255,255,.045) 48px
                );
            opacity: .7;
        }

        /* hanging shirts silhouette strip */
        .login-rails {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 120px;
            pointer-events: none;
            overflow: hidden;
            opacity: .22;
        }
        .login-rails svg {
            width: 140%;
            max-width: none;
            height: 120px;
            animation: rail-drift 28s linear infinite;
        }

        .login-steam {
            position: absolute;
            inset: auto 0 0 0;
            height: 45%;
            pointer-events: none;
            background:
                radial-gradient(ellipse 40% 60% at 20% 100%, rgba(255,255,255,.5) 0%, transparent 70%),
                radial-gradient(ellipse 35% 55% at 55% 100%, rgba(255,255,255,.4) 0%, transparent 65%),
                radial-gradient(ellipse 40% 50% at 85% 100%, rgba(232,244,248,.55) 0%, transparent 70%);
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
            border-radius: 50%;
            border: 1.5px solid rgba(255,255,255,.55);
            background: rgba(255,255,255,.12);
            animation: bubble-up linear infinite;
        }
        .login-bubbles span:nth-child(1) { left: 12%; width: 8px; height: 8px; animation-duration: 11s; animation-delay: 0s; }
        .login-bubbles span:nth-child(2) { left: 28%; width: 14px; height: 14px; animation-duration: 14s; animation-delay: 2s; }
        .login-bubbles span:nth-child(3) { left: 48%; width: 9px; height: 9px; animation-duration: 10s; animation-delay: 4s; }
        .login-bubbles span:nth-child(4) { left: 67%; width: 12px; height: 12px; animation-duration: 13s; animation-delay: 1s; }
        .login-bubbles span:nth-child(5) { left: 84%; width: 7px; height: 7px; animation-duration: 12s; animation-delay: 3.5s; }

        @keyframes rail-drift {
            from { transform: translateX(0); }
            to { transform: translateX(-12%); }
        }
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
        .login-brand__mark {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 8px;
        }
        .login-brand__icon {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            background: linear-gradient(145deg, #2f61bc, #3db8a8);
            color: #fff;
            box-shadow: 0 8px 20px rgba(47, 97, 188, .35);
        }
        .login-brand__icon i { font-size: 20px; }
        .login-brand__name {
            margin: 0;
            font-family: 'Outfit', 'fontku', sans-serif;
            font-size: clamp(2.4rem, 9vw, 3.1rem);
            font-weight: 800;
            letter-spacing: -0.04em;
            line-height: .95;
            color: #0f1c33;
            text-shadow: 0 1px 0 rgba(255,255,255,.35);
        }
        .login-brand__name em {
            font-style: normal;
            color: var(--accent);
        }
        .login-brand__tag {
            margin: 6px 0 0;
            font-size: 14px;
            font-weight: 700;
            color: var(--ink-soft);
            letter-spacing: 0.02em;
        }

        .login-panel {
            background: var(--panel);
            border: 1px solid rgba(255,255,255,.7);
            box-shadow:
                0 20px 40px rgba(26, 39, 64, .14),
                0 1px 0 rgba(255,255,255,.8) inset;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 22px 20px 20px;
            animation: rise-in .7s ease-out .12s both;
        }

        .login-lead {
            margin: 0 0 14px;
            font-size: 13px;
            font-weight: 700;
            color: var(--ink-soft);
            text-align: center;
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
            border: 1.5px solid var(--line);
            background: #fff;
            color: var(--ink);
            font-family: inherit;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            transition: border-color .15s, background .15s, color .15s;
        }
        .login-freq__btn:hover {
            border-color: var(--accent);
            background: #eef4fc;
            color: var(--accent-deep);
        }

        #info {
            margin: 0 0 12px;
        }
        #info:empty { display: none; margin: 0; }
        .login-alert {
            padding: 10px 12px;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.35;
            border: 1px solid transparent;
        }
        .login-alert--err {
            background: #fde8eb;
            border-color: #f0b4bc;
            color: #9b2433;
        }
        .login-alert--ok {
            background: #e5f6ef;
            border-color: #a8dcc4;
            color: #1a6b45;
        }

        .login-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .login-field {
            display: flex;
            align-items: stretch;
            border: 1.5px solid var(--line);
            background: #fff;
            transition: border-color .15s, box-shadow .15s;
        }
        .login-field:focus-within {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(47, 97, 188, .15);
        }
        .login-field input {
            flex: 1 1 auto;
            min-width: 0;
            height: 44px;
            padding: 0 14px;
            border: 0;
            outline: none;
            background: transparent;
            font-family: inherit;
            font-size: 15px;
            font-weight: 700;
            color: var(--ink);
        }
        .login-field input::placeholder {
            color: #94a3b8;
            font-weight: 600;
        }
        .login-field__action,
        .login-field__icon {
            flex: 0 0 44px;
            display: grid;
            place-items: center;
            border: 0;
            border-left: 1.5px solid var(--line);
            background: #f4f7fb;
            color: var(--ink-soft);
            cursor: default;
        }
        .login-field__action {
            cursor: pointer;
            color: var(--accent);
            transition: background .15s, color .15s;
        }
        .login-field__action:hover {
            background: var(--accent);
            color: #fff;
        }
        .login-field__captcha {
            flex: 0 0 56px;
            display: grid;
            place-items: center;
            border-left: 1.5px solid var(--line);
            background: #f8fafc;
            padding: 0 4px;
            cursor: pointer;
        }
        .login-field__captcha img {
            display: block;
            max-height: 32px;
            width: auto;
        }

        .login-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
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
            border: 2.5px solid var(--line);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin .7s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .login-submit {
            min-width: 128px;
            height: 44px;
            padding: 0 22px;
            border: 0;
            background: linear-gradient(135deg, var(--accent) 0%, var(--fresh-deep) 100%);
            color: #fff;
            font-family: 'Outfit', 'fontku', sans-serif;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: 0.02em;
            cursor: pointer;
            box-shadow: 0 8px 18px rgba(47, 97, 188, .28);
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
            font-weight: 700;
            color: rgba(26, 39, 64, .45);
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        @media (max-width: 420px) {
            .login-scene { padding: 20px 12px 28px; }
            .login-panel { padding: 18px 14px 16px; }
            .login-row { grid-template-columns: 1fr; }
        }

        @media (prefers-reduced-motion: reduce) {
            .login-rails svg,
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
        <div class="login-rails" aria-hidden="true">
            <svg viewBox="0 0 1200 120" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
                <line x1="0" y1="18" x2="1200" y2="18" stroke="#1a2740" stroke-width="2"/>
                <!-- hangers + shirts -->
                <g fill="#1a2740">
                    <path d="M70 18v12c0 4-8 8-8 14h40c0-6-8-10-8-14V18"/>
                    <path d="M62 44h32l6 50H56z"/>
                    <path d="M190 18v12c0 4-8 8-8 14h40c0-6-8-10-8-14V18"/>
                    <path d="M182 44h32l4 48h-40z"/>
                    <path d="M310 18v12c0 4-8 8-8 14h40c0-6-8-10-8-14V18"/>
                    <path d="M302 44h32l8 52H294z"/>
                    <path d="M430 18v12c0 4-8 8-8 14h40c0-6-8-10-8-14V18"/>
                    <path d="M422 44h32l5 46h-42z"/>
                    <path d="M550 18v12c0 4-8 8-8 14h40c0-6-8-10-8-14V18"/>
                    <path d="M542 44h32l7 54H535z"/>
                    <path d="M670 18v12c0 4-8 8-8 14h40c0-6-8-10-8-14V18"/>
                    <path d="M662 44h32l4 48h-40z"/>
                    <path d="M790 18v12c0 4-8 8-8 14h40c0-6-8-10-8-14V18"/>
                    <path d="M782 44h32l6 50H776z"/>
                    <path d="M910 18v12c0 4-8 8-8 14h40c0-6-8-10-8-14V18"/>
                    <path d="M902 44h32l5 46h-42z"/>
                    <path d="M1030 18v12c0 4-8 8-8 14h40c0-6-8-10-8-14V18"/>
                    <path d="M1022 44h32l7 54H1015z"/>
                    <path d="M1150 18v12c0 4-8 8-8 14h40c0-6-8-10-8-14V18"/>
                    <path d="M1142 44h32l4 48h-40z"/>
                </g>
            </svg>
        </div>
        <div class="login-steam" aria-hidden="true"></div>
        <div class="login-bubbles" aria-hidden="true">
            <span></span><span></span><span></span><span></span><span></span>
        </div>

        <div class="login-shell">
            <header class="login-brand">
                <div class="login-brand__mark">
                    <div class="login-brand__icon" aria-hidden="true">
                        <i class="fas fa-tshirt"></i>
                    </div>
                </div>
                <h1 class="login-brand__name">MDL <em>Laundry</em></h1>
                <p class="login-brand__tag">Masuk ke kasir outlet</p>
            </header>

            <div class="login-panel">
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
                <?php } else { ?>
                    <p class="login-lead">Login dengan nomor WhatsApp</p>
                <?php } ?>

                <div id="info"></div>

                <form class="login-form" action="<?= URL::BASE_URL ?>Login/cek_login" method="post">
                    <div class="login-field">
                        <input id="hp" type="text" name="username" autocomplete="username" placeholder="Nomor WhatsApp" required>
                        <button type="button" class="login-field__action" id="req_pin" title="Minta PIN via WA">
                            <i class="fas fa-mobile-alt"></i>
                        </button>
                    </div>

                    <div class="login-field">
                        <input type="password" name="pin" placeholder="PIN" required autocomplete="current-password">
                        <span class="login-field__icon"><i class="fas fa-lock"></i></span>
                    </div>

                    <div class="login-row">
                        <div class="login-field">
                            <input type="text" name="outlet" placeholder="ID Outlet" autocomplete="off">
                            <span class="login-field__icon"><i class="fas fa-store-alt"></i></span>
                        </div>
                        <div class="login-field">
                            <input type="text" name="cap" placeholder="Captcha" required autocomplete="off">
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

            <p class="login-foot">Fresh · Clean · Ready</p>
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
            $("input#hp").val($(this).text().trim());
        });
    });
    </script>
<?php require_once __DIR__ . '/pwa_register.php'; ?>
</body>

</html>
