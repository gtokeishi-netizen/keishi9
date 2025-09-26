<?php
/**
 * Test Field Sync Function
 * 
 * Quick test function to verify specific field synchronization
 * Add to functions.php or call directly
 */

// Add this function to the GoogleSheetsSync class or use independently
function gi_test_specific_field_sync() {
    if (!class_exists('GoogleSheetsSync')) {
        return array('error' => 'GoogleSheetsSync class not found');
    }
    
    $sheets_sync = GoogleSheetsSync::getInstance();
    
    // Read sheet data
    $sheet_data = $sheets_sync->read_sheet_data();
    
    if ($sheet_data === false) {
        return array('error' => 'Failed to read sheet data');
    }
    
    if (empty($sheet_data) || count($sheet_data) < 2) {
        return array('error' => 'No data rows found in sheet');
    }
    
    // Remove header row
    $headers = array_shift($sheet_data);
    
    $results = array(
        'total_rows' => count($sheet_data),
        'headers' => $headers,
        'test_results' => array()
    );
    
    // Test first 3 rows
    foreach (array_slice($sheet_data, 0, 3) as $index => $row) {
        $post_id = intval($row[0] ?? 0);
        
        if (!$post_id || !get_post($post_id)) {
            continue;
        }
        
        $row_result = array(
            'post_id' => $post_id,
            'post_title' => get_the_title($post_id),
            'sheet_row' => $index + 2, // Account for header
            'fields' => array()
        );
        
        // Test problematic fields
        $test_fields = array(
            'target_prefecture' => 17, // R column
            'prefecture_name' => 18,   // S column  
            'target_municipality' => 19, // T column
        );
        
        foreach ($test_fields as $field_key => $column_index) {
            $sheet_value = $row[$column_index] ?? '';
            $wp_value = get_field($field_key, $post_id);
            
            $row_result['fields'][$field_key] = array(
                'column' => chr(65 + $column_index), // Convert to letter
                'sheet_value' => $sheet_value,
                'wp_value' => $wp_value,
                'matches' => (string)$sheet_value === (string)$wp_value,
                'sheet_empty' => empty($sheet_value),
                'wp_empty' => empty($wp_value)
            );
        }
        
        // Test category
        $category_sheet = $row[22] ?? ''; // W column
        $category_wp = wp_get_post_terms($post_id, 'grant_category', array('fields' => 'names'));
        $category_wp_str = is_array($category_wp) ? implode(', ', $category_wp) : '';
        
        $row_result['fields']['grant_category'] = array(
            'column' => 'W',
            'sheet_value' => $category_sheet,
            'wp_value' => $category_wp_str,
            'matches' => $category_sheet === $category_wp_str,
            'sheet_empty' => empty($category_sheet),
            'wp_empty' => empty($category_wp_str)
        );
        
        $results['test_results'][] = $row_result;
    }
    
    return $results;
}

// Add AJAX handler for testing
function gi_ajax_test_field_sync() {
    check_ajax_referer('gi_sheets_nonce', 'nonce');
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Permission denied');
    }
    
    $results = gi_test_specific_field_sync();
    
    if (isset($results['error'])) {
        wp_send_json_error($results['error']);
    } else {
        wp_send_json_success($results);
    }
}

add_action('wp_ajax_gi_test_field_sync', 'gi_ajax_test_field_sync');

// Function to manually sync a specific post
function gi_manual_sync_post($post_id) {
    if (!class_exists('GoogleSheetsSync')) {
        return array('error' => 'GoogleSheetsSync class not found');
    }
    
    $sheets_sync = GoogleSheetsSync::getInstance();
    $sheet_data = $sheets_sync->read_sheet_data();
    
    if ($sheet_data === false) {
        return array('error' => 'Failed to read sheet data');
    }
    
    // Find the row for this post
    $target_row = null;
    foreach ($sheet_data as $index => $row) {
        if (intval($row[0] ?? 0) === intval($post_id)) {
            $target_row = $row;
            break;
        }
    }
    
    if (!$target_row) {
        return array('error' => 'Post not found in spreadsheet');
    }
    
    // Manually update the problematic fields
    $updates = array();
    
    // Update ACF fields
    $acf_updates = array(
        'target_prefecture' => $target_row[17] ?? '',
        'prefecture_name' => $target_row[18] ?? '',
        'target_municipality' => $target_row[19] ?? '',
    );
    
    foreach ($acf_updates as $field => $value) {
        $old_value = get_field($field, $post_id);
        $result = update_field($field, $value, $post_id);
        $new_value = get_field($field, $post_id);
        
        $updates[$field] = array(
            'old_value' => $old_value,
            'sheet_value' => $value,
            'new_value' => $new_value,
            'update_result' => $result,
            'success' => (string)$new_value === (string)$value
        );
    }
    
    // Update category
    $category_value = $target_row[22] ?? '';
    if (!empty($category_value)) {
        $categories = array_map('trim', explode(',', $category_value));
        $old_categories = wp_get_post_terms($post_id, 'grant_category', array('fields' => 'names'));
        $result = wp_set_post_terms($post_id, $categories, 'grant_category');
        $new_categories = wp_get_post_terms($post_id, 'grant_category', array('fields' => 'names'));
        
        $updates['grant_category'] = array(
            'old_value' => is_array($old_categories) ? implode(', ', $old_categories) : '',
            'sheet_value' => $category_value,
            'new_value' => is_array($new_categories) ? implode(', ', $new_categories) : '',
            'update_result' => $result,
            'success' => !is_wp_error($result)
        );
    }
    
    return array('success' => true, 'updates' => $updates);
}

// Console output version for direct testing
if (defined('WP_CLI') || (defined('WP_DEBUG') && WP_DEBUG)) {
    function gi_debug_field_sync() {
        $results = gi_test_specific_field_sync();
        
        if (isset($results['error'])) {
            error_log('Field Sync Test Error: ' . $results['error']);
            return;
        }
        
        error_log('Field Sync Test Results:');
        error_log('Total rows: ' . $results['total_rows']);
        
        foreach ($results['test_results'] as $test) {
            error_log('Post ID ' . $test['post_id'] . ' (' . $test['post_title'] . '):');
            
            foreach ($test['fields'] as $field => $data) {
                $status = $data['matches'] ? 'MATCH' : 'MISMATCH';
                error_log("  {$field} ({$data['column']}): Sheet='{$data['sheet_value']}' WP='{$data['wp_value']}' [{$status}]");
            }
        }
    }
}
?>