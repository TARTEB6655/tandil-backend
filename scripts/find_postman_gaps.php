<?php

/** Alias: same as smoke_postman_number_order.php (display-safe E2E). */
passthru('php '.escapeshellarg(__DIR__.'/smoke_postman_number_order.php'), $code);
exit($code);
