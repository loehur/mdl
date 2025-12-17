<script>
    $(document).ready(function() {
        content();
    });

    function content() {
        $("div#content").load('<?= URL::BASE_URL ?><?= $data['page'] ?>/content');
    }
</script>