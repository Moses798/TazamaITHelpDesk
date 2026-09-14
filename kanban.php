<?php
/**
 * "Board View" sidebar link. The kanban board is implemented as a view mode
 * of tickets.php (?view=board) so filters/list/board share one code path;
 * this file just gives it a clean, memorable URL of its own.
 */
$_GET['view'] = 'board';
require __DIR__ . '/tickets.php';
