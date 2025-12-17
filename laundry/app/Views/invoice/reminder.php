<head>
    <meta charset="utf-8">
    <link rel="icon" href="<?= URL::EX_ASSETS ?>icon/logo.png">
    <title>MDL Reminder</title>
    <meta name="viewport" content="width=410, user-scalable=no">
    <link rel="stylesheet" href="<?= URL::EX_ASSETS ?>plugins/bootstrap-5.3/css/bootstrap.min.css">

    <!-- FONT -->
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

        table {
            border-radius: 15px;
            overflow: hidden
        }
    </style>
</head>

<div class="content mt-4">
    <div class="row p-1 mx-1">
        <div class="col m-auto" style="max-width: 480px;">
            <div class="row">
                <div class="col">
                    <b><?= $data['name'] ?></b><br>
                    <?php if ($data['dates'] <= 7) { ?>
                        Deadline <?= $tgl_selesai = date('d-m-Y', strtotime($data['next_date'])) ?><br>
                        <span class="text-<?= $data['class'] ?>"><?= $data['warning'] ?></span>
                    <?php } else { ?>
                        NextDate <?= $tgl_selesai = date('d-m-Y', strtotime($data['next_date'])) ?><br>
                        <span class="text-success ?>">Selesai</span>
                    <?php } ?>

                </div>
                <div class="col-auto">
                    <?php if ($data['dates'] <= 7) { ?>
                        <span class="btn btn-outline-success" onclick="update(<?= $data['id'] ?>)">Tandai<br>Selesai</span>
                    <?php } else { ?>
                        <span class="btn btn-outline-secondary disabled">Reminder<br>Done</span>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= URL::EX_ASSETS ?>js/jquery-3.6.0.min.js"></script>

<script>
    function update(id_) {
        $.ajax({
            url: '<?= URL::BASE_URL ?>Reminder/update',
            data: {
                id: id_,
            },
            type: 'POST',
            success: function(res) {
                if (res == 0) {
                    location.reload(true);
                } else {
                    alert(res);
                }
            },
        });
    }
</script>