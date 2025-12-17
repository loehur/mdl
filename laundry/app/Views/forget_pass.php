<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MDL | Lupa Password</title>

    <link rel="icon" href="<?= URL::EX_ASSETS ?>icon/logo.png">
    <script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>

    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&amp;display=fallback">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-F3w7mX95PdgyTmZZMECAngseQB83DfGTowi0iMjiWaeVhAn4FJkqJByhZMI3AhiU" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= URL::EX_ASSETS ?>css/ionicons.min.css">
    <link href="<?= URL::EX_ASSETS ?>plugins/fontawesome-free-5.15.4-web/css/all.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= URL::EX_ASSETS ?>plugins/adminLTE-3.1.0/css/adminlte.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Titillium+Web&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Titillium Web',
                sans-serif;
        }
    </style>
</head>


<script>
    $(document).ready(function() {
        $("#info").fadeOut();

        $('#req_code').click(function() {
            var getEmail = $('#email').val();

            if (getEmail.length == 0) {
                $("#info").hide();
                $("#info").fadeIn(1000);
                $("#info").html('<div class="alert alert-danger" role="alert">Silahkan Isi Email Terlebih Dahulu!</div>');
                return;
            }

            $.ajax({
                url: "<?= URL::BASE_URL ?>Register/req_code",
                data: {
                    email: getEmail
                },
                type: "POST",
                dataType: 'html',

                success: function(response) {
                    if (response == 1) {
                        $("#info").hide();
                        $("#info").fadeIn(1000);
                        $("#info").html('<div class="alert alert-success" role="alert">Request Code Sukses. Silahkan Cek Email!</div>');
                    } else {
                        $("#info").hide();
                        $("#info").fadeIn(1000);
                        $("#info").html('<div class="alert alert-success" role="alert">' + response + '</div>');
                    }
                },
            });
        })

        $('#form').validate({
            rules: {
                email: {
                    required: true,
                    email: true
                },
                reset_code: {
                    required: true
                },
                password: {
                    required: true,
                },
                repass: {
                    required: true,
                    equalTo: '#password'
                }
            },

            submitHandler: function(form) {
                $.ajax({
                    url: $("#form").attr('action'),
                    data: $("#form").serialize(),
                    type: $("#form").attr("method"),
                    dataType: 'html',

                    success: function(response) {
                        if (response == 1) {
                            $("#info").hide();
                            $('form').trigger("reset");
                            $("#info").fadeIn(1000);
                            $("#info").html('<div class="alert alert-success" role="alert">Password Baru Sukses, Silahkan Login!</div>')
                        } else {
                            $("#info").hide();
                            $("#info").fadeIn(1000);
                            $("#info").html('<div class="alert alert-danger" role="alert">' + response + '</div>')
                        }
                    },
                });
            }
        });


    });
</script>

<body class="login-page" style="min-height: 496.781px;">
    <div class="login-box">
        <div class="login-logo">
            <a href="#">MDL <b>Laundry</b></a>
        </div>
        <!-- /.login-logo -->
        <div class="card">
            <div class="card-body register-card-body small">
                <p class="login-box-msg">Register a new membership</p>

                <!-- ALERT -->
                <div id="info"></div>

                <form id="form" action="<?= URL::BASE_URL ?>Register/updatePass" method="post">
                    <div class="row mb-2">
                        <div class="col">
                            <input type="email" class="form-control" id="email" name="email" placeholder="Email">
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col">
                            <input type="text" class="form-control" id="reset_code" name="reset_code" placeholder="Code">
                        </div>
                        <div class="col-4">
                            <a id="req_code" class="btn btn-dark btn-block">Request Code</a>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col">
                            <input type="password" class="form-control" id="password" name="password" placeholder="Password Baru">
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col">
                            <input type="password" class="form-control" id="repass" name="repass" placeholder="Retype password">
                        </div>
                    </div>
                    <div class="row mb-2">
                        <!-- /.col -->
                        <div class="col-4">
                            <button type="submit" class="btn btn-primary btn-block">Save</button>
                        </div>
                        <!-- /.col -->
                    </div>
                </form>

                Sudah ingat Password?<a href="<?= URL::BASE_URL ?>Login" class="text-center"> LOGIN</a>

                <div class="error"><span></span></div>
            </div>
            <!-- /.form-box -->
        </div>
    </div>

</body>

</html>