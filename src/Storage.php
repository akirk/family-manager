<?php

namespace FamilyManager;

class Storage {
    private const MEMBER_TAXONOMY = 'family_member';

    public function get_or_create_household_for_user( int $user_id ): array {
        $households = get_posts( [
            'author'           => $user_id,
            'fields'           => 'ids',
            'meta_key'         => '_family_manager_owner_user_id',
            'meta_value'       => $user_id,
            'numberposts'      => 1,
            'orderby'          => 'ID',
            'order'            => 'ASC',
            'post_status'      => 'private',
            'post_type'        => 'family_household',
            'suppress_filters' => false,
        ] );

        if ( $households ) {
            return $this->format_household( (int) $households[0] );
        }

        $user = get_userdata( $user_id );
        $name = $user && $user->display_name ? sprintf( __( '%s Household', 'family-manager' ), $user->display_name ) : __( 'My Household', 'family-manager' );
        $household_id = wp_insert_post( [
            'post_author' => $user_id,
            'post_status' => 'private',
            'post_title'  => $name,
            'post_type'   => 'family_household',
        ], true );

        if ( is_wp_error( $household_id ) ) {
            return [];
        }

        update_post_meta( $household_id, '_family_manager_owner_user_id', $user_id );
        $this->seed_household( (int) $household_id );

        return $this->format_household( (int) $household_id );
    }

    public function user_can_access_household( int $user_id, int $household_id ): bool {
        $household = get_post( $household_id );

        return $household && 'family_household' === $household->post_type && (int) $household->post_author === $user_id;
    }

    public function get_dashboard( int $user_id ): array {
        $household = $this->get_or_create_household_for_user( $user_id );
        $household_id = isset( $household['id'] ) ? (int) $household['id'] : 0;

        if ( ! $household_id ) {
            return [
                'household' => [],
                'members'   => [],
                'tasks'     => [],
                'rewards'   => [],
            ];
        }

        return [
            'household' => $household,
            'members'   => $this->get_members( $household_id ),
            'tasks'     => $this->get_tasks( $household_id ),
            'rewards'   => $this->get_rewards( $household_id ),
        ];
    }

    public function get_members( int $household_id ): array {
        $terms = get_terms( [
            'hide_empty' => false,
            'meta_key'   => '_family_manager_household_id',
            'meta_value' => $household_id,
            'taxonomy'   => self::MEMBER_TAXONOMY,
        ] );

        if ( is_wp_error( $terms ) ) {
            return [];
        }

        $members = array_map( [ $this, 'format_member' ], $terms );
        usort( $members, static function( array $a, array $b ): int {
            return strcasecmp( $a['name'], $b['name'] );
        } );

        return $members;
    }

    public function get_tasks( int $household_id ): array {
        $task_ids = $this->get_related_posts( $household_id, 'family_task' );
        $tasks = array_map( [ $this, 'format_task' ], $task_ids );

        usort( $tasks, static function( array $a, array $b ): int {
            if ( $a['is_done'] !== $b['is_done'] ) {
                return (int) $a['is_done'] <=> (int) $b['is_done'];
            }

            return strcmp( $a['due_date'] ?: '9999-12-31', $b['due_date'] ?: '9999-12-31' );
        } );

        return $tasks;
    }

    public function get_rewards( int $household_id ): array {
        $reward_ids = $this->get_related_posts( $household_id, 'family_reward' );

        return array_map( [ $this, 'format_reward' ], $reward_ids );
    }

    public function add_member( int $household_id, string $name, string $role = 'child' ): int {
        $term = wp_insert_term( $name, self::MEMBER_TAXONOMY, [
            'slug' => $this->get_member_slug( $household_id, $name ),
        ] );

        if ( is_wp_error( $term ) ) {
            if ( 'term_exists' !== $term->get_error_code() ) {
                return 0;
            }

            $term_id = (int) $term->get_error_data();
        } else {
            $term_id = (int) $term['term_id'];
        }

        update_term_meta( $term_id, '_family_manager_household_id', $household_id );
        update_term_meta( $term_id, '_family_manager_role', $role );
        update_term_meta( $term_id, '_family_manager_points', (int) get_term_meta( $term_id, '_family_manager_points', true ) );

        return $term_id;
    }

    public function add_task( int $household_id, string $title, int $member_id = 0, string $task_type = 'task', int $points = 0, string $due_date = '' ): int {
        $member_id = $this->normalize_member_id( $household_id, $member_id );
        $task_id = wp_insert_post( [
            'post_author' => $this->get_household_owner_user_id( $household_id ),
            'post_parent' => $household_id,
            'post_status' => 'private',
            'post_title'  => $title,
            'post_type'   => 'family_task',
        ], true );

        if ( is_wp_error( $task_id ) ) {
            return 0;
        }

        update_post_meta( $task_id, '_family_manager_household_id', $household_id );
        update_post_meta( $task_id, '_family_manager_task_type', in_array( $task_type, [ 'task', 'appointment' ], true ) ? $task_type : 'task' );
        update_post_meta( $task_id, '_family_manager_points', $points );
        update_post_meta( $task_id, '_family_manager_due_date', $this->normalize_date( $due_date ) );
        update_post_meta( $task_id, '_family_manager_is_done', 0 );
        $this->assign_member( $task_id, $member_id );

        return (int) $task_id;
    }

    public function toggle_task( int $household_id, int $task_id ): bool {
        $task = get_post( $task_id );

        if ( ! $task || 'family_task' !== $task->post_type || (int) get_post_meta( $task_id, '_family_manager_household_id', true ) !== $household_id ) {
            return false;
        }

        $is_done = (int) get_post_meta( $task_id, '_family_manager_is_done', true ) ? 0 : 1;
        update_post_meta( $task_id, '_family_manager_is_done', $is_done );
        update_post_meta( $task_id, '_family_manager_completed_at', $is_done ? current_time( 'mysql' ) : '' );

        $member = $this->get_assigned_member( $task_id );
        $points = (int) get_post_meta( $task_id, '_family_manager_points', true );

        if ( $member && $points ) {
            $current_points = (int) get_term_meta( $member->term_id, '_family_manager_points', true );
            update_term_meta( $member->term_id, '_family_manager_points', $current_points + ( $is_done ? $points : -1 * $points ) );
        }

        return true;
    }

    public function add_reward( int $household_id, string $title, int $member_id = 0, int $points = 0 ): int {
        $member_id = $this->normalize_member_id( $household_id, $member_id );
        $reward_id = wp_insert_post( [
            'post_author' => $this->get_household_owner_user_id( $household_id ),
            'post_parent' => $household_id,
            'post_status' => 'private',
            'post_title'  => $title,
            'post_type'   => 'family_reward',
        ], true );

        if ( is_wp_error( $reward_id ) ) {
            return 0;
        }

        update_post_meta( $reward_id, '_family_manager_household_id', $household_id );
        update_post_meta( $reward_id, '_family_manager_points', $points );
        update_post_meta( $reward_id, '_family_manager_redeemed_at', '' );
        $this->assign_member( $reward_id, $member_id );

        return (int) $reward_id;
    }

    private function get_related_posts( int $household_id, string $post_type ): array {
        return get_posts( [
            'fields'           => 'ids',
            'meta_key'         => '_family_manager_household_id',
            'meta_value'       => $household_id,
            'numberposts'      => -1,
            'orderby'          => 'date',
            'order'            => 'DESC',
            'post_status'      => 'private',
            'post_type'        => $post_type,
            'suppress_filters' => false,
        ] );
    }

    private function format_household( int $household_id ): array {
        $household = get_post( $household_id );

        if ( ! $household ) {
            return [];
        }

        return [
            'id'            => $household_id,
            'name'          => $household->post_title,
            'owner_user_id' => (int) $household->post_author,
            'created_at'    => $household->post_date,
        ];
    }

    private function format_member( $term ): array {
        return [
            'id'           => (int) $term->term_id,
            'household_id' => (int) get_term_meta( $term->term_id, '_family_manager_household_id', true ),
            'name'         => $term->name,
            'role'         => get_term_meta( $term->term_id, '_family_manager_role', true ) ?: 'child',
            'points'       => (string) (int) get_term_meta( $term->term_id, '_family_manager_points', true ),
        ];
    }

    private function format_task( int $task_id ): array {
        $member = $this->get_assigned_member( $task_id );

        return [
            'id'           => $task_id,
            'household_id' => (int) get_post_meta( $task_id, '_family_manager_household_id', true ),
            'member_id'    => $member ? (int) $member->term_id : 0,
            'member_name'  => $member ? $member->name : '',
            'title'        => get_post_field( 'post_title', $task_id ),
            'task_type'    => get_post_meta( $task_id, '_family_manager_task_type', true ) ?: 'task',
            'points'       => (string) (int) get_post_meta( $task_id, '_family_manager_points', true ),
            'due_date'     => get_post_meta( $task_id, '_family_manager_due_date', true ),
            'is_done'      => (string) (int) get_post_meta( $task_id, '_family_manager_is_done', true ),
        ];
    }

    private function format_reward( int $reward_id ): array {
        $member = $this->get_assigned_member( $reward_id );

        return [
            'id'           => $reward_id,
            'household_id' => (int) get_post_meta( $reward_id, '_family_manager_household_id', true ),
            'member_id'    => $member ? (int) $member->term_id : 0,
            'member_name'  => $member ? $member->name : '',
            'title'        => get_post_field( 'post_title', $reward_id ),
            'points'       => (string) (int) get_post_meta( $reward_id, '_family_manager_points', true ),
            'redeemed_at'  => get_post_meta( $reward_id, '_family_manager_redeemed_at', true ),
        ];
    }

    private function seed_household( int $household_id ): void {
        $child_id = $this->add_member( $household_id, __( 'Mia', 'family-manager' ), 'child' );
        $this->add_member( $household_id, __( 'Ben', 'family-manager' ), 'child' );
        $this->add_task( $household_id, __( 'Pack school bag', 'family-manager' ), $child_id, 'task', 5, gmdate( 'Y-m-d' ) );
        $this->add_task( $household_id, __( 'Dentist appointment', 'family-manager' ), 0, 'appointment', 0, gmdate( 'Y-m-d', strtotime( '+2 days' ) ) );
        $this->add_reward( $household_id, __( 'Movie night pick', 'family-manager' ), $child_id, 20 );
    }

    private function normalize_member_id( int $household_id, int $member_id ): int {
        if ( ! $member_id ) {
            return 0;
        }

        return (int) get_term_meta( $member_id, '_family_manager_household_id', true ) === $household_id ? $member_id : 0;
    }

    private function assign_member( int $post_id, int $member_id ): void {
        if ( $member_id ) {
            wp_set_object_terms( $post_id, [ $member_id ], self::MEMBER_TAXONOMY, false );
            return;
        }

        wp_set_object_terms( $post_id, [], self::MEMBER_TAXONOMY, false );
    }

    private function get_assigned_member( int $post_id ) {
        $terms = wp_get_object_terms( $post_id, self::MEMBER_TAXONOMY );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return null;
        }

        return $terms[0];
    }

    private function get_member_slug( int $household_id, string $name ): string {
        return sanitize_title( 'household-' . $household_id . '-' . $name );
    }

    private function get_household_owner_user_id( int $household_id ): int {
        $household = get_post( $household_id );

        return $household ? (int) $household->post_author : 0;
    }

    private function normalize_date( string $date ): string {
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            return $date;
        }

        return '';
    }
}
