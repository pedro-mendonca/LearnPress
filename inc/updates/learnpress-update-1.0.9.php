<?php

if ( LEARN_PRESS_UPDATE_DATABASE ) {

	error_reporting( 0 );

	global $wpdb;

	$wpdb->query( "START TRANSACTION;" );

	try {
		$wpdb->query( "TRUNCATE {$wpdb->prefix}learnpress_user_items" );
		$wpdb->query( "TRUNCATE {$wpdb->prefix}learnpress_user_itemmeta" );
		$query = $wpdb->prepare( "
			INSERT INTO {$wpdb->prefix}learnpress_user_items(`user_item_id`, `user_id`, `item_id`, `item_type`, `start_time`, `end_time`, `status`, `ref_id`, `ref_type`)
			(
				SELECT uq.user_quiz_id, uq.user_id, uq.quiz_id, %s, FROM_UNIXTIME(uqm1.meta_value) as start_date, FROM_UNIXTIME(uqm2.meta_value) as start_date, uqm3.meta_value as status, 0, %s
				FROM {$wpdb->prefix}learnpress_user_quizzes uq
				INNER JOIN {$wpdb->prefix}learnpress_user_quizmeta uqm1 ON uq.user_quiz_id = uqm1.learnpress_user_quiz_id AND uqm1.meta_key = %s
				INNER JOIN {$wpdb->prefix}learnpress_user_quizmeta uqm2 ON uq.user_quiz_id = uqm2.learnpress_user_quiz_id AND uqm2.meta_key = %s
				INNER JOIN {$wpdb->prefix}learnpress_user_quizmeta uqm3 ON uq.user_quiz_id = uqm3.learnpress_user_quiz_id AND uqm3.meta_key = %s
			)
		", 'lp_quiz', 'lp_course', 'start', 'end', 'status' );
		$wpdb->query( $query );

		$query = $wpdb->prepare( "
			INSERT INTO {$wpdb->prefix}learnpress_user_itemmeta(`learnpress_user_item_id`, `meta_key`, `meta_value`)
			SELECT learnpress_user_quiz_id, meta_key, meta_value
			FROM {$wpdb->prefix}learnpress_user_quizmeta
			WHERE meta_key <> %s AND meta_key <> %s AND meta_key <> %s
		", 'start', 'end', 'status' );
		$wpdb->query( $query );

		//fix course_id is empty in quiz item
		$query = $wpdb->prepare( "
			SELECT user_item_id, item_id
			FROM {$wpdb->learnpress_user_items}
			WHERE ref_id = %d
		", 0 );
		LP_Debug::instance()->add( $query );
		if ( $item_empty_course = $wpdb->get_results( $query ) ) {

			$q_vars = array();
			foreach ( $item_empty_course as $r ) {
				$q_vars[] = $r->item_id;
			}
			$in    = array_fill( 0, sizeof( $q_vars ), '%d' );
			$query = $wpdb->prepare( "
				SELECT section_course_id as course_id, item_id
				FROM {$wpdb->learnpress_section_items} si
				INNER JOIN {$wpdb->learnpress_sections} s ON si.section_id = s.section_id
				WHERE item_id IN(" . join( ',', $in ) . ")
			", $q_vars );

			if ( $item_courses = $wpdb->get_results( $query ) ) {
				foreach ( $item_courses as $row ) {
					$wpdb->update(
						$wpdb->learnpress_user_items,
						array( 'ref_id' => $row->course_id ),
						array( 'ref_id' => 0, 'item_id' => $row->item_id ),
						array( '%d' ),
						array( '%d', '%d' )
					);
				}
			}
		}

		$query = $wpdb->prepare( "
			INSERT INTO {$wpdb->prefix}learnpress_user_items(`user_id`, `item_id`, `item_type`, `start_time`, `end_time`, `status`, `ref_id`, `ref_type`)
			SELECT `user_id`, `course_id`, %s, `start_time`, `end_time`, `status`, `order_id`, %s
			FROM {$wpdb->prefix}learnpress_user_courses
		", 'lp_course', 'lp_order' );
		$wpdb->query( $query );

		$query = $wpdb->prepare( "
			INSERT INTO {$wpdb->prefix}learnpress_user_items(`user_id`, `item_id`, `item_type`, `start_time`, `end_time`, `status`, `ref_id`, `ref_type`)
			SELECT user_id, lesson_id, %s, if(start_time, start_time, %s), if(end_time, end_time, %s), status, course_id, %s
			FROM {$wpdb->prefix}learnpress_user_lessons w;
		", 'lp_lesson', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'lp_course' );
		$wpdb->query( $query );
		// remove auto-increment
		//$query = "ALTER TABLE {$wpdb->prefix}learnpress_user_courses` MODIFY COLUMN `user_course_item_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0;";

		// remove auto-increment
		$query = "ALTER TABLE {$wpdb->prefix}learnpress_user_course_items` MODIFY COLUMN `user_course_item_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0;";
		$wpdb->query( $query );

		learn_press_update_log( '1.0.9', array( 'time' => time() ) );
	} catch ( Exception $ex ) {
		$wpdb->query( "ROLLBACK;" );
	}
}