<?php

defined('ABSPATH') || exit;

class WCRD_Dispute_Manager
{

    private static function table()
    {
        global $wpdb;
        return $wpdb->prefix . 'rental_disputes';
    }

    public static function create_database()
    {
        global $wpdb;

        $table = self::table();
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            vendor_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            dispute_by ENUM('vendor','customer') NOT NULL,
            dispute_reason TEXT NULL,
            deposit_amount DECIMAL(10,2) NOT NULL,
            status ENUM('open','under_review','resolved') DEFAULT 'open',
            admin_decision ENUM('pending','vendor','customer','partial') DEFAULT 'pending',
            admin_note TEXT NULL,
            messages LONGTEXT NULL,
            evidence LONGTEXT NULL,
            transaction_data LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY vendor_id (vendor_id),
            KEY customer_id (customer_id),
            KEY status (status)
        ) $charset_collate;";

        dbDelta($sql);
    }

    /**
     * CREATE DISPUTE
     */
    public static function create($data)
    {
        global $wpdb;

        $table = self::table();

        $inserted = $wpdb->insert($table, [
            'order_id'        => $data['order_id'],
            'product_id'      => $data['product_id'],
            'vendor_id'       => $data['vendor_id'],
            'customer_id'     => $data['customer_id'],
            'dispute_by'      => $data['dispute_by'],
            'dispute_reason'  => $data['dispute_reason'] ?? '',
            'deposit_amount'  => $data['deposit_amount'],
            'status'          => 'open',
            'admin_decision'  => 'pending',
            'messages'        => json_encode([]),
            'evidence'        => json_encode($data['evidence'] ?? []),
            'transaction_data' => json_encode([]),
        ], [
            '%d',
            '%d',
            '%d',
            '%d',
            '%s',
            '%s',
            '%f',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s'
        ]);

        if (!$inserted) return false;

        return $wpdb->insert_id;
    }

    /**
     * UPDATE DISPUTE (Generic)
     */
    public static function update($dispute_id, $data)
    {
        global $wpdb;

        $table = self::table();

        if (isset($data['messages']) && is_array($data['messages'])) {
            $data['messages'] = json_encode($data['messages']);
        }

        if (isset($data['evidence']) && is_array($data['evidence'])) {
            $data['evidence'] = json_encode($data['evidence']);
        }

        if (isset($data['transaction_data']) && is_array($data['transaction_data'])) {
            $data['transaction_data'] = json_encode($data['transaction_data']);
        }

        return $wpdb->update(
            $table,
            $data,
            ['id' => $dispute_id]
        );
    }

    /**
     * DELETE DISPUTE
     */
    public static function delete($dispute_id)
    {
        global $wpdb;

        $table = self::table();

        return $wpdb->delete($table, [
            'id' => $dispute_id
        ], ['%d']);
    }

    /**
     * ADD MESSAGE (APPEND JSON)
     */
    public static function add_message($dispute_id, $user_id, $message)
    {
        global $wpdb;

        $table = self::table();

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT messages FROM $table WHERE id = %d", $dispute_id)
        );

        $messages = $row && $row->messages ? json_decode($row->messages, true) : [];

        $messages[] = [
            'user_id' => $user_id,
            'message' => $message,
            'time'    => current_time('mysql')
        ];

        return $wpdb->update($table, [
            'messages' => json_encode($messages)
        ], ['id' => $dispute_id]);
    }

    /**
     * ADD EVIDENCE
     */
    public static function add_evidence($dispute_id, $files = [])
    {
        global $wpdb;

        $table = self::table();

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT evidence FROM $table WHERE id = %d", $dispute_id)
        );

        $existing = $row && $row->evidence ? json_decode($row->evidence, true) : [];

        $updated = array_merge($existing, $files);

        return $wpdb->update($table, [
            'evidence' => json_encode($updated)
        ], ['id' => $dispute_id]);
    }

    /**
     * RESOLVE DISPUTE
     */
    public static function resolve($dispute_id, $decision, $note = '', $transaction_data = [])
    {
        global $wpdb;

        $table = self::table();

        return $wpdb->update($table, [
            'status'           => 'resolved',
            'admin_decision'   => $decision,
            'admin_note'       => $note,
            'transaction_data' => json_encode($transaction_data)
        ], ['id' => $dispute_id]);
    }

    /**
     * GET SINGLE DISPUTE
     */
    public static function get($dispute_id)
    {
        global $wpdb;

        $table = self::table();

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $dispute_id)
        );
    }

    /**
     * GET DISPUTES (Optional filters)
     */
    public static function get_all($args = [])
    {
        global $wpdb;

        $table = self::table();

        $where = "WHERE 1=1";

        if (!empty($args['status'])) {
            $where .= $wpdb->prepare(" AND status = %s", $args['status']);
        }

        if (!empty($args['vendor_id'])) {
            $where .= $wpdb->prepare(" AND vendor_id = %d", $args['vendor_id']);
        }

        if (!empty($args['customer_id'])) {
            $where .= $wpdb->prepare(" AND customer_id = %d", $args['customer_id']);
        }

        return $wpdb->get_results("SELECT * FROM $table $where ORDER BY id DESC");
    }
}
