<?php
if (!isset($_SESSION['mgrValidated'])) {
    die();
}

$idsa = array();
foreach ($configuration as $conf) {
    if (($conf['type'] == 'ids') && ($conf['value']) && ($conf['show_child'] == 0)) {
        $idsa[] = $conf['value'];
    }

    if (($conf['type'] == 'template') && ($conf['value']) && ($conf['show_child'] == 0)) {
        $idsa[] = $modx->db->getValue('SELECT GROUP_CONCAT(id) FROM ' . $modx->getFullTableName('site_content') . ' WHERE template IN (' . $conf['value'] . ')');
    }
}

$ids = implode(',', $idsa);
if (!$ids) {
    return;
}

foreach (explode(',', $ids) as $i) {
    $i = trim($i);
    if ($i) {
        if ($ph['id'] == $i) {
            $ph['showChildren'] = '0';
        }
    }
}

$modx->event->output(serialize($ph));
