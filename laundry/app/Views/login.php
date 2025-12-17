<html lang="en">

<head>
    <meta charset="utf-8">
    <link rel="icon" href="<?= URL::EX_ASSETS ?>icon/logo.png">
    <title>MDL LAUNDRY</title>
    <meta name="viewport" content="width=410, user-scalable=no">
    <script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= URL::EX_ASSETS ?>css/ionicons.min.css">
    <link href="<?= URL::EX_ASSETS ?>plugins/fontawesome-free-5.15.4-web/css/all.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= URL::EX_ASSETS ?>plugins/adminLTE-3.1.0/css/adminlte.min.css">

    <style>
        @font-face {
            font-family: "fontku";
            src: url("<?= URL::EX_ASSETS ?>font/Titillium-Regular.otf");
        }

        html .table {
            font-family: 'fontku', sans-serif;
        }

        html .content {
            font-family: 'fontku', sans-serif;
        }

        html body {
            font-family: 'fontku', sans-serif;
        }

        @media print {
            p div {
                font-family: 'fontku', sans-serif;
                font-size: 14px;
            }
        }

        .modal-backdrop {
            opacity: 0.1 !important;
        }
    </style>
</head>


<script>
    $(document).ready(function() {
        $("#info").hide();
        $("#spinner").hide();
        $("form").on("submit", function(e) {
            $("#spinner").show();
            e.preventDefault();
            $.ajax({
                url: $(this).attr('action'),
                data: $(this).serialize(),
                type: $(this).attr("method"),

                success: function(res) {
                    try {
                        data = JSON.parse(res);
                        if (data.code == 0) {
                            $("#info").hide();
                            $("#info").html('<div class="alert alert-danger" role="alert">' + data.msg + '</div>')
                            $("#info").fadeIn();
                            $("#spinner").hide();
                        } else if (data.code == 1) {
                            $("#info").hide();
                            $("#info").html('<div class="alert alert-success" role="alert">' + data.msg + '</div>')
                            $("#info").fadeIn();
                            $("#spinner").hide();
                        } else if ((data.code == 11)) {
                            location.reload(true);
                        } else if ((data.code == 10)) {
                            $("#captcha").attr('src', '<?= URL::BASE_URL ?>Login/captcha');
                            $("#info").hide();
                            $("#info").html('<div class="alert alert-danger" role="alert">' + data.msg + '</div>')
                            $("#info").fadeIn();
                            $("#spinner").hide();
                        }
                    } catch (e) {
                        $("#info").hide();
                        $("#info").html('<div class="alert alert-danger" role="alert">' + res + '</div>')
                        $("#info").fadeIn();
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
                data: {
                    hp: hp_input
                },
                type: 'POST',

                success: function(res) {
                    try {
                        data = JSON.parse(res);
                        if (data.code == 0) {
                            $("#info").hide();
                            $("#info").html('<div class="alert alert-danger" role="alert">' + data.msg + '</div>')
                            $("#info").fadeIn();
                            $("#spinner").hide();
                        } else if (data.code == 1) {
                            $("#info").hide();
                            $("#info").html('<div class="alert alert-success" role="alert">' + data.msg + '</div>')
                            $("#info").fadeIn();
                            $("#spinner").hide();
                        } else if ((data.code == 11)) {
                            location.reload(true);
                        } else if ((data.code == 10)) {
                            $("#captcha").attr('src', '<?= URL::BASE_URL ?>Login/captcha');
                            $("#info").hide();
                            $("#info").html('<div class="alert alert-danger" role="alert">' + data.msg + '</div>')
                            $("#info").fadeIn();
                            $("#spinner").hide();
                        }
                    } catch (e) {
                        $("#info").hide();
                        $("#info").html('<div class="alert alert-danger" role="alert">' + res + '</div>')
                        $("#info").fadeIn();
                        $("#spinner").hide();
                    }
                },
            });
        });

        $(".freq_number").click(function() {
            $("input#hp").val($(this).html());
        })
    });
</script>

<body class="login-page small" style="min-height: 496.781px;">
    <div class="login-box">
        <div class="login-logo">
            <a href="#">MDL <span class="text-info">Login</span></a><br>
        </div>
        <!-- /.login-logo -->
        <div class="card border border-info rounded">
            <div class="card-body login-card-body rounded shadow px-3">
                <?php if (count($data) > 0) { ?>
                    <p class="text-center mb-2">Choose frequently whatsapp numbers login</p>
                    <div class="row row-cols-3 mx-0 px-0 mb-3">
                        <?php
                        if (is_array($data)) {
                            krsort($data, 1);
                        }
                        foreach ($data as $ntm) { ?>
                            <div class="col text-center px-1 py-1" style="cursor: pointer">
                                <div class="freq_number border rounded"><?= $ntm ?></div>
                            </div>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <p class="text-center mb-2">Login new session with whatsapp number</p>
                <?php } ?>

                <div id="info" class="px-1"></div>
                <div class="px-1">
                    <form action="<?= URL::BASE_URL ?>Login/cek_login" method="post">
                        <div class="input-group mb-3">
                            <input id="hp" type="text" name="username" class="form-control" autocomplete="username" placeholder="Nomor Whatsapp" required>
                            <div class="input-group-append">
                                <div class="input-group-text" id="req_pin" style="cursor: pointer; width:40px">
                                    <div class="w-100 text-center">
                                        <span><i class="fas fa-mobile-alt"></i></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="input-group mb-3">
                            <input type="password" name="pin" class="form-control" placeholder="PIN" required>
                            <div class="input-group-append">
                                <div class="input-group-text" style="width:40px">
                                    <div class="w-100 text-center">
                                        <span class="fas fa-lock"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col pe-0">
                                <div class="input-group mb-3">
                                    <input type="text" name="outlet" class="form-control" placeholder="ID Outlet">
                                    <div class="input-group-append">
                                        <div class="input-group-text" style="width:40px">
                                            <div class="w-100 text-center">
                                                <span class="fas fa-store-alt"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="input-group mb-3">
                                    <input type="text" name="cap" class="form-control" placeholder="Captcha" required>
                                    <div class="input-group-append">
                                        <div class="input-group-text px-0" style="width:40px">
                                            <div class="w-100 text-center">
                                                <img id="captcha" src="<?= URL::BASE_URL ?>Login/captcha" alt="captcha" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col text-end text-primary">
                                <div id="spinner" class="spinner-border" role="status">
                                    <span class="sr-only">Loading...</span>
                                </div>
                            </div>
                            <div class="col">
                                <button type="submit" class="btn btn-primary btn-block">Login</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>

</html>