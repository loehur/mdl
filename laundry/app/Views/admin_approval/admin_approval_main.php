<?php
$array = array(0 => 'Setoran', 1 => 'NonTunai', 2 => 'HapusOrder', 3 => 'HapusDeposit', 4 => 'Pengeluaran')
?>

<div class="row mx-0 py-2 px-2 bg-warning-subtle position-sticky" style="top: 70px; z-index: 100;">
    <?php
    $classActive = "";
    foreach ($array as $a) { ?>
        <div class="col-auto px-1 my-2" style="white-space: nowrap;">
            <?php $count = count($data[$a]);
            $classActive = ($a == $data['mode']) ? "bg-white shadow-sm" : "";
            ?>
            <a href="<?= URL::BASE_URL ?>AdminApproval/index/<?= $a ?>" class="border border-warning rounded-2 px-3 py-1 text-decoration-none <?= $classActive ?>">
                <?php if ($count > 0) { ?>
                    <span class="fw-bold"><?= $a ?></span> <span class="badge bg-danger"><?= $count ?></span>
                <?php } else { ?>
                    <span class="text-muted"><?= $a ?></span> <i class="text-success fas fa-check-circle"></i>
                <?php } ?>
            </a>
        </div>
    <?php }
    ?>
</div>

<div class="row mx-0 mt-3" style="max-width: 500px;">
    <div class="col px-2 pt-1" id="load">
    </div>
</div>


<script>
    $(document).ready(function() {
        loadContent('<?= $data['mode'] ?>');
    });

    function loadContent(mode) {
        $(".loaderDiv").fadeIn("fast");
        $("div#load").load("<?= URL::BASE_URL ?>" + mode);
        $(".loaderDiv").fadeOut("slow");
    }
</script>