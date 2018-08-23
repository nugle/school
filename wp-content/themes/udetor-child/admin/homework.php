<?php

        add_action('admin_head', 'custom_colors');
        function custom_colors() {
        echo '<style type="text/css">
        .menu-icon-lessons{
            margin-top:15px !important;
            border-top:1px solid #ccc;
        }
        #menu-posts-forms{
            margin-bottom:15px !important;
            border-bottom:1px solid #ccc !important;
        }
        </style>';
        }

    //добавление своего метабокса start
    function fl_homeworks_add_metabox(){
        add_meta_box('before_publish', 'Поиск', 'fl_homeworks_metabox_content', 'homeworks', 'side', 'high');
    }
    function fl_homeworks_metabox_content() {
        echo '<a class="button button-primary button-large" href="'.$_SERVER['HTTP_REFERER'].'">Вернуться к поиску</a>';
    }
    add_action('add_meta_boxes', 'fl_homeworks_add_metabox');
    //добавление своего метабокса end

    add_action('restrict_manage_posts', 'add_event_table_filters_homeworks');
    function add_event_table_filters_homeworks($post_type) {
      if($post_type == 'homeworks'){

          $courses = Courses::getCourses();

          echo '<select name="course">
                  <option value="">Все курсы</option>
          ';
              foreach($courses as $course){
                  echo '<option value="'.$course->term_id.'" '.selected($course->term_id, $_GET['course'], 0).'>'.$course->name.'</option>';
              }
          echo '</select>';

          $course = isset($_GET['course']) ? (int)$_GET['course'] : 0;

          $lessons = $course == 0 ? Courses::getAllLessons() : Courses::getLessonsByCourse($course);

          echo '<select name="lesson">
                  <option value="">Все уроки</option>
          ';
              foreach($lessons as $lesson){
                  echo '<option value="'.$lesson->ID.'" '.selected($lesson->ID, $_GET['lesson'], 0).'>'.get_the_title($lesson->ID).'</option>';
              }
          echo '</select>';

          $statuses = Courses::$statuses;
          echo '<select name="status">
                  <option value="">Все статусы</option>
          ';

              foreach($statuses as $key => $status){
                  echo '<option value="'.$key.'" '.selected($key, $_GET['status'], 0).'>'.$status['text'].'</option>';
              }
          echo '</select>';

          $users = get_users();

          echo '<select name="user">
                  <option value="">Все пользователи</option>
          ';
              foreach($users as $user){
                  echo '<option value="'.$user->ID.'" '.selected($user->ID, $_GET['user'], 0).'>'.$user->display_name.'</option>';
              }

          echo '</select>';



      }

    }

    add_action( 'pre_get_posts', 'admin_homeworks_pre_get_posts' );
    function admin_homeworks_pre_get_posts($query) {
    	if( ! is_admin() || !$query->is_main_query()) return; // выходим если не админка

      $metaQuery = array();
      $post_type = $query->query_vars['post_type'];

      if($post_type == 'homeworks'){
          if(isset($_GET['status']) && !empty($_GET['status'])){
              $metaQuery[] = array(
                'key' => 'status',
                'value' => $_GET['status']
              );
          }

          if(isset($_GET['lesson']) && !empty($_GET['lesson'])){
              $metaQuery[] = array(
                'key' => 'lesson',
                'value' => $_GET['lesson']
              );
          }

          if(isset($_GET['course']) && !empty($_GET['course'])){
              $lessons = Courses::getLessonsByCourse($_GET['course']);

              if(count($lessons) > 0){
                  $ids = array();
                  foreach($lessons as $lesson){
                      $ids[] = $lesson->ID;
                  }

                  $metaQuery[] = array(
                    'key' => 'lesson',
                    'value' => $ids,
                    'compare' => 'IN'
                  );
              }


          }

          if(isset($_GET['user']) && $_GET['user'] > 0){
              $metaQuery[] = array(
                  'key' => 'user',
                  'value' => (int)$_GET['user']
              );
          }

          if(count($metaQuery) > 0){
              $query->set('meta_query', $metaQuery);
          }
      }
    }

    add_filter('manage_homeworks_posts_columns', 'add_views_column', 4);
    function add_views_column( $columns ){
        $num = 2; // после какой по счету колонки вставлять новые

        $new_columns = array(
            'users' => 'Пользователь',
            'status' => 'Статус',
            'lesson' => 'Урок',
            'courses' => 'Курсы'
        );

        return array_slice( $columns, 0, $num ) + $new_columns + array_slice( $columns, $num );
    }

    // заполняем колонку данными
    add_filter('manage_homeworks_posts_custom_column', 'fill_views_column', 5, 2); // wp-admin/includes/class-wp-posts-list-table.php
    function fill_views_column($colname, $id_post){

        if($colname === 'users'){
            $user = get_field('user', $id_post);
            echo '<a href="'.$_SERVER['REQUEST_URI'].'&user='.$user['ID'].'">'.$user['display_name'].'</a>';
        }

        if($colname === 'status'){
            $statuses = Courses::$statuses;
            $status = get_field('status', $id_post);
            $text_status = isset($statuses[$status]) ? $statuses[$status]['text'] : '';
            echo $text_status;
        }

        if($colname === 'lesson'){
            $lesson = get_post(get_field('lesson', $id_post));
            echo '<a href="'.get_permalink($lesson->ID).'" target="_blank">'.$lesson->post_title.'</a>';
        }

        if($colname === 'courses'){
            $lesson = get_field('lesson', $id_post);
            if($lesson){
                $courses = wp_get_post_terms($lesson->ID, 'courses');
                $text_courses = '';
                if(count($courses) > 0){
                    $arrayCourses = array();
                    foreach($courses as $course){
                        $arrayCourses[] = '<a href="'.get_term_link($course->term_id).'" target="_blank">'.$course->name.'</a>';
                    }
                    $text_courses = implode(', ', $arrayCourses);
                }
                echo $text_courses;
            }
        }

    }

    add_filter('manage_edit-courses_columns', 'add_views_courses_columns');

    function add_views_courses_columns($columns){

        $columns['autotraining'] = 'Автотренинг';
        $columns['type'] = 'Тип курса';

        return $columns;

    }

    add_filter('manage_courses_custom_column', 'fill_views_courses_columns', 0, 3);

    function fill_views_courses_columns($content, $colname, $term_id){
        if($colname == 'autotraining'){
            $autotraining = get_field('autotraining', 'courses_'.$term_id);
            $content = 'Нет';
            if($autotraining && $autotraining == true) $content = 'Да';
        }

        if($colname == 'type'){
            $type = get_field('type', 'courses_'.$term_id);
            $content = $type && $type == 'free' ? 'Бесплатный' : 'Платный';
        }

        return $content;

    }
