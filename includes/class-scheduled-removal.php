<?php
/**
 * Scheduled removal engine.
 *
 * Schedules and executes automatic unpublishing of posts based on the
 * meom_scheduled_removal meta value.
 *
 * @package Meom\Scheduled_Removal
 */

namespace Meom\Scheduled_Removal;

/**
 * Handles (re)scheduling and execution of post removal.
 */
class Scheduled_Removal {

    /**
     * Register hooks.
     */
    public function __construct() {
        add_action( 'added_post_meta', array( $this, 'on_meta_event' ), 10, 4 );
        add_action( 'updated_post_meta', array( $this, 'on_meta_event' ), 10, 4 );
        add_action( 'deleted_post_meta', array( $this, 'on_meta_event' ), 10, 4 );
        add_action( CRON_HOOK, array( $this, 'remove_post' ) );
        add_action( SWEEP_HOOK, array( $this, 'sweep' ) );
    }

    /**
     * React to any add/update/delete of our removal-date meta by reconciling
     * the cron schedule.
     *
     * The three metadata hooks share the same argument positions for object ID,
     * meta key and value; the differing first argument ( $meta_id vs $meta_ids )
     * is unused here, so one callback serves all three.
     *
     * @param int|string[] $meta_id_or_ids Meta row ID, or array of IDs ( deleted_post_meta ).
     * @param int          $post_id        Post ID.
     * @param string       $meta_key       Meta key.
     * @return void
     */
    public function on_meta_event( $meta_id_or_ids, $post_id, $meta_key ): void {
        if ( META_KEY !== $meta_key ) {
            return;
        }
        $this->reconcile( (int) $post_id );
    }

    /**
     * Reconcile the cron schedule for a post against its current meta value.
     *
     * Always clears any existing event first, then reschedules (or removes
     * immediately if the time has already passed).
     *
     * @param int $post_id Post ID.
     * @return void
     */
    public function reconcile( int $post_id ): void {
        if ( $post_id <= 0 || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
        }

        wp_clear_scheduled_hook( CRON_HOOK, array( $post_id ) );

        $value = get_post_meta( $post_id, META_KEY, true );

        if ( empty( $value ) ) {
            return;
        }

        $date = \DateTime::createFromFormat( DATE_FORMAT, $value, wp_timezone() );

        if ( ! $date ) {
            return;
        }

        $timestamp = $date->getTimestamp();

        if ( $timestamp <= time() ) {
            $this->remove_post( $post_id );
            return;
        }

        wp_schedule_single_event( $timestamp, CRON_HOOK, array( $post_id ) );
    }

    /**
     * Cron callback: set a published post to draft and clear its schedule.
     *
     * Runs without a user session. The status change happens first; the meta is
     * deleted only after a successful update. If the update fails, the meta is
     * retained so the hourly sweep can retry later. Deleting the meta fires
     * deleted_post_meta -> on_meta_event -> reconcile, but by then the value is
     * gone and the post is already draft, so that path is a harmless no-op.
     *
     * @param int $post_id Post ID to remove.
     * @return void
     */
    public function remove_post( int $post_id ): void {
        if ( 'publish' !== get_post_status( $post_id ) ) {
            return;
        }

        $result = wp_update_post(
            array(
                'ID'          => $post_id,
                'post_status' => 'draft',
            ),
            true
        );

        if ( is_wp_error( $result ) ) {
            error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                sprintf(
                    'Meom Scheduled Removal: failed to set post %d to draft: %s',
                    $post_id,
                    $result->get_error_message()
                )
            );
            return;
        }

        // Clear the schedule only after the post is safely unpublished.
        delete_post_meta( $post_id, META_KEY );
        clean_post_cache( $post_id );
    }

    /**
     * Hourly sweep: remove any past-due published posts whose single event was missed.
     *
     * Uses CHAR comparison because the stored value is a fixed-width ISO string
     * ("Y-m-d\TH:i:s"); such strings sort chronologically, and CAST AS DATETIME
     * does not reliably parse the "T" separator across MySQL versions.
     *
     * @return void
     */
    public function sweep(): void {
        $now = ( new \DateTime( 'now', wp_timezone() ) )->format( DATE_FORMAT );

        $query = new \WP_Query(
            array(
                'post_type'      => get_supported_post_types(),
                'post_status'    => 'publish',
                // Cap each sweep; any overflow is caught by the next hourly run.
                'posts_per_page' => 100,
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                    array(
                        'key'     => META_KEY,
                        'value'   => $now,
                        'compare' => '<=',
                        'type'    => 'CHAR',
                    ),
                ),
            )
        );

        foreach ( $query->posts as $post_id ) {
            $this->remove_post( (int) $post_id );
        }
    }
}
