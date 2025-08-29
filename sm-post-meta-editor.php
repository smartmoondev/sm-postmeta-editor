<?php

/**
 * Plugin Name: SmartMoon Post Meta Editor
 * Description: Edit post meta fields easily.
 * Version: 0.0.1-delta
 * Author: SmartMoon
 */

 define('SM_POST_META_EDITOR_MAX_LENGTH', 500);
 define('SM_POST_META_EDITOR_UNWANTED_KEYS', 
    ['_wp_attachment_metadata']
);

 if (!defined('SM_CORE_LOADED')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p><strong>SmartMoon Post Meta Editor</strong> requires <strong>SmartMoon Core</strong> to be active.</p></div>';
    });
    return;
}

add_action('admin_menu', 'sm_post_meta_editor_add_submenu');

function sm_post_meta_editor_add_submenu() 
{
    add_submenu_page(
        'sm-core',
        'Post Meta Editor',
        'Post Meta Editor',
        'manage_options',
        'sm-post-meta-editor',
        'sm_post_meta_editor_page_content'
    );
}

function sm_post_meta_editor_page_content() {
    if (isset($_GET['action']) && $_GET['action'] === 'import_all_postmeta') {
        import_all_postmeta();
    }
    else
    {
    global $wpdb;    
    ?>
    <div class="wrap">
        <h1>Post Meta Editor</h1>
            <p>Edit post meta fields easily.</p>
            <a href="<?= admin_url('admin.php?page=sm-post-meta-editor') ?>" class="button button-primary">Home</a>
        <?php 
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { 
            if(isset($_POST['postmeta_meta_value'])) {
               
                foreach($_POST['postmeta_meta_value'] as $meta_id => $meta_value) {                    
                    $postMeta = $wpdb->get_row($wpdb->prepare("SELECT meta_key, post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_id = %d", $meta_id));
                    if (!$postMeta) continue;

                    $metaId = $meta_id;
                    $postId = $postMeta->post_id;
                    $metaKey = $postMeta->meta_key;
                    $oldValue  = $postMeta->meta_value;
                    $value = stripslashes($meta_value);                    

                    if ($oldValue != $value) {                                                           
                        update_post_meta($postId, $metaKey, $value);
                    }
                }
            }
        }
        
        ?>
        
        <?php
        $all_postmeta = $wpdb->get_results("SELECT * FROM {$wpdb->postmeta}");
        ?>
        
        <?php if ($all_postmeta) { ?>
        <a class="button button-primary" id="export_all_postmeta" href="<?= admin_url('admin-ajax.php?action=sm_post_meta_editor_export_all_postmeta') ?>">Export All Postmeta</a>
        <a class="button button-primary" id="import_all_postmeta" href="<?= admin_url('admin.php?page=sm-post-meta-editor&action=import_all_postmeta') ?>">Import All Postmeta</a>

     

        <br><br>
        <form method="post">
            <table class="widefat striped">
            <thead>
                <tr>
                    <th>Meta ID</th>
                    <th>Post ID</th>
                    <th>Meta Key</th>
                    <th>Meta Value</th> 
                    <th>New Value</th> 
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_postmeta as $postmeta) { ?>
                    <?php if(strlen($postmeta->meta_value) <= SM_POST_META_EDITOR_MAX_LENGTH && !in_array($postmeta->meta_key, SM_POST_META_EDITOR_UNWANTED_KEYS)) { ?>
                    <tr>
                        <td style="max-width:50px!important;"><?= $postmeta->meta_id ?></td>
                        <td style="max-width:50px!important;"><?= $postmeta->post_id ?></td>
                        <td style="min-width:50px!important;max-width:50px!important;"><?= $postmeta->meta_key ?></td>
                        <td style="max-width:100px!important;"><?= htmlentities($postmeta->meta_value) ?></td>
                        <td style="max-width:600px!important;"> 
                            <!-- if meta value is array, then make it textarea -->                                                    
                                <input type="text" 
                                style="width:600px!important;" 
                                name="postmeta_meta_value[<?= $postmeta->meta_id ?>]" 
                                id="postmeta_meta_value" 
                                class="form-control" 
                                value="<?= htmlentities($postmeta->meta_value) ?>">                          
                        </td>
                    </tr>  
                    <?php } ?>
                <?php }?>
              </tbody>
              </table> 
              <br>    
              <button type="submit" class="button button-primary" id="save_postmeta_all">Save All</button>       
        </form>
        <?php } else { ?>
            <p>No post meta fields found.</p>        
        <?php } ?>

    </div>

    <?php
    }
}

add_action('wp_ajax_sm_post_meta_editor_export_all_postmeta', 'sm_post_meta_editor_export_all_postmeta');

function sm_post_meta_editor_export_all_postmeta() {
    global $wpdb;
    $all_postmeta = $wpdb->get_results("SELECT * FROM {$wpdb->postmeta}");

    //Remove from all_postmeta if length is greater than SM_POST_META_EDITOR_MAX_LENGTH
    foreach($all_postmeta as $postmeta) {
        if(strlen($postmeta->meta_value) > SM_POST_META_EDITOR_MAX_LENGTH || in_array($postmeta->meta_key, SM_POST_META_EDITOR_UNWANTED_KEYS)) {
            unset($all_postmeta[$postmeta->meta_id]);
        }
    }
    
    // convert array to json
    $all_postmeta = json_encode($all_postmeta);
    
    // download as csv
    if (ob_get_level() === 0) ob_start();
    header('Content-Type: application/csv');
    header('Content-Disposition: attachment; filename="all_postmeta.csv"');
    echo $all_postmeta;
    ob_end_flush();
    wp_die();
}

function import_all_postmeta() {
    ?>
    <h1>Post Meta Editor</h1>
            <p>Import All Post Meta</p>
            <a href="<?= admin_url('admin.php?page=sm-post-meta-editor') ?>" class="button button-primary">Home</a>


        <hr class="wp-header-end">
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_FILES['import_postmeta_file']) && $_FILES['import_postmeta_file']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['import_postmeta_file']['tmp_name'];
                $fileContent = file_get_contents($fileTmpPath);
                $imported_data = json_decode($fileContent, true);

                if (is_array($imported_data)) {
                    global $wpdb;
                    // Build a set of unique keys from imported data: post_id + meta_key
                    $imported_keys = [];
                    foreach ($imported_data as $meta) {
                        $post_id = isset($meta['post_id']) ? $meta['post_id'] : 0;
                        $meta_key = isset($meta['meta_key']) ? $meta['meta_key'] : '';
                        $imported_keys["{$post_id}||{$meta_key}"] = [
                            'post_id'    => $post_id,
                            'meta_key'   => $meta_key,
                            'meta_value' => isset($meta['meta_value']) ? $meta['meta_value'] : '',
                        ];
                    }

                    // Get all current postmeta keys from DB
                    $db_postmeta = $wpdb->get_results("SELECT meta_id, post_id, meta_key, meta_value FROM {$wpdb->postmeta}", ARRAY_A);
                    $db_keys = [];
                    $db_meta_ids = [];
                    foreach ($db_postmeta as $row) {
                        $key = "{$row['post_id']}||{$row['meta_key']}";
                        $db_keys[$key] = $row;
                        $db_meta_ids[$key] = $row['meta_id'];
                    }

                    // 1. Update existing, insert new
                    foreach ($imported_keys as $key => $meta) {
                        if (isset($db_keys[$key])) {
                            // Exists: update if value changed
                            if ($db_keys[$key]['meta_value'] !== $meta['meta_value']) {
                                $wpdb->update(
                                    $wpdb->postmeta,
                                    array('meta_value' => $meta['meta_value']),
                                    array(
                                        'meta_id' => $db_keys[$key]['meta_id']
                                    )
                                );
                            }
                            // Remove from $db_keys so we know which to delete later
                            unset($db_keys[$key]);
                        } else {
                            // Not exists: insert
                            $wpdb->insert(
                                $wpdb->postmeta,
                                array(
                                    'post_id'    => $meta['post_id'],
                                    'meta_key'   => $meta['meta_key'],
                                    'meta_value' => $meta['meta_value'],
                                )
                            );
                        }
                    }

                    // 2. Delete records in DB not present in import
                    if (!empty($db_keys)) {
                        $meta_ids_to_delete = array_column($db_keys, 'meta_id');
                        if (!empty($meta_ids_to_delete)) {
                            $ids_str = implode(',', array_map('intval', $meta_ids_to_delete));
                            $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_id IN ($ids_str)");
                        }
                    }
                    echo '<br><br><div class="notice notice-success is-dismissible"><p>Post meta imported successfully.</p></div>';
                } else {
                    echo '<br><br><div class="notice notice-error is-dismissible"><p>Invalid file format. Please upload a valid JSON export.</p></div>';
                }
            } else {
                echo '<br><br><div class="notice notice-error is-dismissible"><p>No file uploaded or upload error.</p></div>';
            }
        }
        ?>
        <form method="post" enctype="multipart/form-data" style="margin-top: 20px;">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="import_postmeta_file">Select File</label></th>
                    <td>
                        <input type="file" name="import_postmeta_file" id="import_postmeta_file" accept=".json,.csv" required>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" class="button button-primary" value="Import">
            </p>
        </form>
    </div>
    <?php
    wp_die();
}
