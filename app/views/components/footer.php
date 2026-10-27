<?php

foreach ($pageScripts as $script):
?>
    <script src="<?= BASE_URL . ltrim($script, '/') ?>"></script>
<?php endforeach; ?>

</body>
</html>
