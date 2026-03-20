<?php

declare(strict_types=1);

/**
 * If the web server document root points at the project folder (not `public/`),
 * visiting /…/cp-promptx/ runs this file and sends users to the real front controller.
 */
header('Location: public/', true, 302);
exit;
