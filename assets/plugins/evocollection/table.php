<?php
if (!isset($_SESSION['mgrValidated'])) {
    die();
}
if (!$_GET['id']) {
    return;
}

if ($_GET['a'] == 4) {
    return;
}

// действия
if (!empty($_GET['act']) && !empty($_GET['docid'])) {
    $ids = implode(',', $_GET['docid']);
    if ($ids) {
        // удалить/восстановить
        if ($_GET['act'] == 'del') {
            $modx->db->query('UPDATE ' . $modx->getFullTableName('site_content') . ' SET deleted=1 WHERE id IN (' . $ids . ')');
        }

        if ($_GET['act'] == 'restore') {
            $modx->db->query('UPDATE ' . $modx->getFullTableName('site_content') . ' SET deleted=0 WHERE id IN (' . $ids . ')');
        }

        // бубликация
        if ($_GET['act'] == 'pub') {
            $modx->db->query('UPDATE ' . $modx->getFullTableName('site_content') . ' SET published=1, publishedon="' . time() . '", publishedby=' . $modx->getLoginUserID() . ' WHERE id IN (' . $ids . ')');
        }

        if ($_GET['act'] == 'unpub') {
            $modx->db->query('UPDATE ' . $modx->getFullTableName('site_content') . ' SET published=0, publishedon=0, publishedby=0 WHERE id IN (' . $ids . ')');
        }
    }
}

$cf = array();
$output = '';
$tid = $modx->db->getValue('SELECT template FROM ' . $modx->getFullTableName('site_content') . ' WHERE id=' . $id);

foreach ($configuration as $key => $conf) {
    if (($conf['type'] == 'ids') && ($conf['value'])) {
        $arr = explode(',', $conf['value']);
        if (in_array($id, $arr)) {
            if (count($conf['fields'])) {
                $fields_a = array(
                    'id',
                );
                foreach ($conf['fields'] as $k => $v) {
                    if ($k != 'id') {
                        $fields_a[] = $k;
                    }
                }

                $fields = implode(',', $fields_a);
                $idc = $key;
                break;
            }
        }
    }
    if (($conf['type'] == 'template') && ($conf['value'])) {
        $arr = explode(',', $conf['value']);
        if (in_array($tid, $arr)) {
            if (count($conf['fields'])) {
                $fields_a = array(
                    'id',
                );
                foreach ($conf['fields'] as $k => $v) {
                    if ($k != 'id') {
                        $fields_a[] = $k;
                    }
                }

                $fields = implode(',', $fields_a);
                $idc = $key;
                break;
            }
        }
    }
}
if (!isset($fields)) {
    return;
}

// Field for sort
if (!empty($_GET['sorter'])) {
    $sorter = $_GET['sorter'];
} else {
    if ($configuration[$idc]['sort']) {
        $c = $modx->db->getValue('SELECT count(*) FROM ' . $modx->getFullTableName('site_tmplvars') . ' WHERE name="' . $configuration[$idc]['sort'] . '"');
        if ($c) {
            $soretr = 'tv.' . $configuration[$idc]['sort'];
        } else {
            if ($modx->db->getValue('SHOW columns FROM ' . $modx->getFullTableName('site_content') . ' WHERE field="' . $configuration[$idc]['sort'] . '"')) {
                $sorter = 'c.' . $configuration[$idc]['sort'];
            } else {
                $sorter = 'c.pagetitle';
            }
        }
    } else {
        $sorter = 'c.pagetitle';
    }
}

// direction for sort
if (!empty($_GET['direction'])) {
    $direction = $_GET['direction'];
} else {
    if ($configuration[$idc]['direction']) {
        $direction = $configuration[$idc]['direction'];
    }
}

// Limit to show
if ($configuration[$idc]['limit']) {
    $limit = $configuration[$idc]['limit'];
} else {
    $limit = 10;
}

if (!empty($_GET['show'])) {
    if ($_GET['show'] != 'all') {
        $start = ($_GET['page'] - 1) * $limit;
        if ($_GET['page']) {
            $l = 'LIMIT ' . $start . ', ' . $_GET['show'];
        } else {
            $l = 'LIMIT 0,' . $_GET['show'];
        }
    }
} else {
    if (!empty($_GET['page'])) {
        $start = ($_GET['page'] - 1) * $limit;
    } else {
        $start = 0;
    }
    if (!empty($_GET['page'])) {
        $l = 'LIMIT ' . $start . ', ' . $limit;
    } else {
        $l = 'LIMIT 0,' . $limit;
    }
}

$prefix = $modx->db->config['table_prefix'];
$lng = include MODX_BASE_PATH . MGR_DIR . '/includes/lang/' . $modx->config['manager_language'] . '.inc.php';

$res = $modx->db->query('SELECT `column_name`,`column_type` FROM INFORMATION_SCHEMA.Columns WHERE table_name = "' . $prefix . 'site_content" ORDER BY ordinal_Position');
while ($row = $modx->db->getRow($res)) {
    $at = explode('(', $row['column_type']);
    $type = $at[0];
    if (!empty($at[1])) {
        $len = str_replace(')', '', $at[1]);
        if ($len == 1) {
            $type = 'yn';
        }
        // Yes/No - checkbox
    }
    if (($type == 'int') or ($type == 'tinyint')) {
        $type = 'number';
    }

    if (($type == 'varchar') or ($type == 'text')) {
        $type = 'text';
    }

    if (!empty($_lang[$row['column_name']])) {
        $caption = $_lang[$row['column_name']];
    } else {
        $caption = $row['column_name'];
    }

    $cf[$row['column_name']] = array(
        'type' => $type,
        'length' => $len,
        'caption' => $caption,
    );
}

$res = $modx->db->query('SELECT `id`,`type`,`name`,`caption`,`description`,`elements`,`display_params` FROM ' . $modx->getFullTableName('site_tmplvars'));

while ($row = $modx->db->getRow($res)) {
    $delim = explode('&format=', $row['display_params']);
    if (!empty($delim[1])) {
        $delimiter = $delim[1];
    } else {
        $delimiter = '||';
    }

    $cf[$row['name']] = array(
        'type' => $row['type'],
        'table' => 'tv',
        'tmplvarid' => $row['id'],
        'caption' => $row['caption'],
        'elements' => $row['elements'],
        'delimiter' => $delimiter,
    );
}

$array = explode(',', $fields);

$tv_fields = array();
$tv_join = array();
$c_fields = array();
$ff = array();
foreach ($array as $key => $val) {
    if (!empty($cf[$val]['table']) == 'tv') {
        $tv_fields[] = "tv" . $key . ".value as '" . $val . "'";
        $tv_join[] = "LEFT JOIN " . $modx->getFullTableName('site_tmplvar_contentvalues') . " as tv" . $key . " ON c.id = tv" . $key . ".contentId and tv" . $key . ".tmplvarid = " . $cf[$val]['tmplvarid'];
        $ff[] = $val;
    } else if (!empty($cf[$val])) {
        $c_fields[] = 'c.' . $val;
        $ff[] = $val;
    }
}

$fa_sql = array_merge($tv_fields, $c_fields);

$fsql = implode(',', $fa_sql);
if (!array_key_exists('id', $fa_sql)) {
    $fsql = 'c.id,' . $fsql;
}

if (!array_key_exists('deleted', $fa_sql)) {
    $fsql = 'c.deleted,' . $fsql;
}

if (!empty($_GET['onlyid'])) {
    $onlyid = 'and c.id=' . $_GET['onlyid'];
    $getsr = 'id="getstr"';
}

if (!empty($_GET['search'])) {
    $search = 'AND (c.pagetitle LIKE "%' . $modx->db->escape($_GET['search']) . '%" OR c.longtitle LIKE "%' . $modx->db->escape($_GET['search']) . '%")';
}

// главный запрос
$sql = "SELECT SQL_CALC_FOUND_ROWS " . $fsql . " FROM " . $modx->getFullTableName('site_content') . " as c " . implode(' ', $tv_join) . " WHERE c.parent=" . $id . " " . ($onlyid ?? '') . " " . ($search ?? '') . " ORDER BY " . $sorter . " " . $direction . " " . $l;

$tbl = '<div class="row"><div class="table-responsive"><table class="table data" id="table_doc"><thead><tr class="">';

// Head table
foreach ($ff as $f) {
    if (!empty($config[$idc]['fields'][$f]['title'])) {
        $title = $config[$idc]['fields'][$f]['title'];
    } else {
        $title = $cf[$f]['caption'];
    }

    if (!empty($config[$idc]['fields'][$f]['width'])) {
        $width = $config[$idc]['fields'][$f]['width'];
    } else {
        $width = '';
    }

    if (!empty($cf[$f]['table']) && $cf[$f]['table'] != 'tv') {
        $url = $modx->config['site_manager_url'];
        $url .= '?a=27&id=' . $_GET['id'];
        if (!empty($_GET['show']) && $_GET['show']) {
            $url .= '&show=' . $_GET['show'];
        }

/*        // почему-то было сделано вот так:
$url .= '&sorter=c.' . $cf[$f]['caption'];
 */
        $url .= '&sorter=c.' . $f;
        if (!empty($_GET['direction']) && $_GET['direction'] == 'asc') {
            $url .= '&direction=desc';
            if ($_GET['sorter'] == 'c.' . $f) {
                $di = '<i class="fa fa-sort-amount-asc" aria-hidden="true"></i>';
            } else {
                $di = '';
            }
        } else {
            $url .= '&direction=asc';
            if (!empty($_GET['sorter']) && $_GET['sorter'] == 'c.' . $f) {
                $di = '<i class="fa fa-sort-amount-desc" aria-hidden="true"></i>';
            } else {
                $di = '';
            }
        }

        $caption = '<a href="' . $url . '">' . $title . ' ' . $di . '</a>';
    } else {
        $caption = $title;
    }

    $tbl .= '<td width="' . $width . '">' . $caption . '</td>';
}

$tbl .= '<th width="1%"></th><th><input type="checkbox" id="checkall" ></th></tr></thead><tbody>';

$res = $modx->db->query($sql);
$arr = $modx->db->makeArray($res);

// количестов элементов с учётом фильтра
$cq = $modx->db->getValue($modx->db->query('SELECT FOUND_ROWS()'));

if ($config[$idc]['new_doc'] == 'up') {
    $tbl .= '<tr id="newstrbutt"><td colspan="' . (count($ff) + 1) . '"></td><td><i class="fa fa-plus" aria-hidden="true" id="news_str" data-template="' . $configuration[$idc]['template_default'] . '" data-parent="' . $_GET['id'] . '"></i><i class="fa fa-spinner fa-spin"  id="spiner_new_str"></i></td></tr>';
}

$hiddenStr = 0;
for ($i = 0; $i < count($arr); $i++) {
    $row = $arr[$i];

    if ($row['deleted'] == 1) {
        $deltr = "style='background:pink;'";
    } else {
        $deltr = '';
    }

    $tbl .= '<tr data-id="' . $row['id'] . '" ' . $deltr . ' ' . ($getsr ?? '') . '>';
    foreach ($ff as $f) {

        if (!empty($cf[$f]['table']) && $cf[$f]['table'] == 'tv') {
            $table = "tv";
        } else {
            $table = "content";
        }

        if (!empty($config[$idc]['fields'][$f]['type']) && $config[$idc]['fields'][$f]['type'] != 'default') {
            $type = $config[$idc]['fields'][$f]['type'];
        } else {
            $type = $cf[$f]['type'];
        }

        if (!empty($config[$idc]['fields'][$f]['type']) && $config[$idc]['fields'][$f]['type'] == 'user') {
            $user = $config[$idc]['fields'][$f]['user'];
        } else {
            $user = '';
        }

        if ($f == 'id') {
            $tbl .= '<td>' . $row[$f] . '</td>';
        } else {
            $tbl .= '
                <td>
                <div class="ecoll_field"><div class="input"
                data-id="' . $row['id'] . '"
                data-table="' . $table . '"
                data-field="' . $f . '"
                data-elements="' . (isset($cf[$f]['elements']) ? $modx->db->escape($cf[$f]['elements']) : '') . '"
                data-delimiter="' . (isset($cf[$f]['delimiter']) ? $modx->db->escape($cf[$f]['delimiter']) : '') . '"
                data-user_func="' . $user . '"
                data-type="' . $type . '"
                >' . get_output(array(
                'did' => $row['id'],
                'value' => $row[$f],
                'field' => $f,
                'table' => $table,
                'type' => $type,
                'elements' => $cf[$f]['elements'] ?? '',
                'delimiter' => $cf[$f]['delimiter'] ?? '',
                'user_func' => $user,
                'mode' => 'input',
            )) . '</div>

                <div class="output">' . get_output(array(
                'did' => $row['id'],
                'value' => $row[$f],
                'field' => $f,
                'table' => $table,
                'elements' => $cf[$f]['elements'] ?? '',
                'delimiter' => $cf[$f]['delimiter'] ?? '',
                'type' => $type,
                'user_func' => $user,
                'mode' => 'output',
            )) . '</div></div>
            </td>';
        }
    }
    $tbl .= '<td><div class="actions text-center text-nowrap"><a href="index.php?a=27&amp;id=' . $row['id'] . '&amp;dir=DESC&amp;sort=createdon" title="Редактировать"><i class="fa fa-pencil-square-o"></i></a><a href="' . $modx->makeurl($row['id'], '', '', 'full') . '" target="_blank"><i class="fa fa-eye" aria-hidden="true"></i></a></div></td><td><input type="checkbox" name="docid[]" value="' . $row['id'] . '" class="docid"></td></tr>';
}

if ($config[$idc]['new_doc'] == 'down') {
    $tbl .= '<tr id="newstrbutt"><td colspan="' . (count($ff) + 1) . '"></td><td><i class="fa fa-plus" aria-hidden="true" id="news_str" data-template="' . $configuration[$idc]['template_default'] . '" data-parent="' . $_GET['id'] . '"></i><i class="fa fa-spinner fa-spin"  id="spiner_new_str"></i></td></tr>';
}

$tbl .= '</tbody></table></div></div>';

if ($config[$idc]['title']) {
    $title = $config[$idc]['title'];
} else {
    $title = $modx->db->getValue('SELECT `pagetitle` FROM ' . $modx->getFullTableName('site_content') . ' WHERE id=' . $id);
}

$output .= '
    <div class="tab-page" id="tabProducts">
    <h2 class="tab">' . $title . '</h2>

    <div class="row mb-2">
        <div class="row-col col-lg-12 col-12 text-right">
            <input type="text" name="search" id="search" placeholder="поиск по названию" value="' . (!empty($_GET['search']) ?? '') . '" style="width: 250px;" />

            <select name="show" id="show" style="width:150px;">
                <option value="">Показывать по</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="150">150</option>
                <option value="250">250</option>
                <option value="500">500</option>
                <option value="1000">1000</option>
            </select>

            <select name="act" id="act" style="width:200px; margin-right: 5px;">
                <option value="">Действия</option>
                <option value="del">Удалить</option>
                <option value="restore">Восстановить</option>
                <option value="unpub">Снять с публикации</option>
                <option value="pub">Опубликовать</option>
            </select>

            <a class="btn btn-success" href="javascript:;" onclick="act();" style="width:100px; float:right;"><span>Применить</span></a>
        </div>
    </div>

    <div class="row">
        <div class="row-col col-lg-12 col-12 text-right">
    ' . $tbl . '
        </div>
    </div>
';

// переход на sql_calc_found_rows
//$cq = $modx->db->getValue('SELECT count(*) FROM ' . $modx->getFullTableName('site_content') . ' WHERE parent=' . $id);
if (!empty($_GET['show'])) {
    $limit = $_GET['show'];
}

$pages = ceil($cq / $limit);

if ($pages > 1) {
    $output .= '<div class="row">
<div class="row-col col-lg-12 col-12">
<div id="pagination" style="text-align:center;"><ul>';

    for ($i = 1; $i <= $pages; $i++) {
        $url = $modx->config['site_manager_url'] . '?a=' . $_GET['a'] . '&id=' . $_GET['id'];
        if (!empty($_GET['show'])) {
            $url .= '&show=' . $_GET['show'];
        }

        if (!empty($_GET['order'])) {
            $url .= '&order=' . $_GET['order'];
        }

        if (!empty($_GET['search'])) {
            $url .= '&search=' . $_GET['search'];
        }

        $output .= '<li><a href="' . $url . '&page=' . $i . '">' . $i . '</a></li>';
    }
    $output .= '</ul></div></div></div>';
}
$output .= '</div>';

if ($configuration[$idc]['template_default']) {
    $template = $configuration[$idc]['template_default'];
} else {
    $template = $modx->db->getValue('SELECT template FROM ' . $modx->getFullTableName('site_content') . ' WHERE parent=' . $_GET['id']);
}

$output .= '<div id="popup_rich"><div id="close"><i class="fa fa-close"></i></div><h2>Редактирование содержимого</h2>
<div id="rta"></div><div style="text-align:center; margin-top:10px;"><a  class="btn btn-success save_content">Сохранить</a></div></div></div>

<link rel="stylesheet" type="text/css" href="/assets/plugins/evocollection/js/evocollection.css">
<script>
manager_url = "' . $modx->config['site_manager_url'] . '";
how_click = "' . $config[$idc]['how_edit'] . '";
new_doc = "' . $config[$idc]['new_doc'] . '";
</script>
<script src="/assets/plugins/evocollection/js/evocollection.js?v=1.0b" type="text/javascript"></script>
<script src="/assets/plugins/tinymce4/tinymce/tinymce.min.js"></script>';

$modx->event->output($output);
