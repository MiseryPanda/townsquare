<?php
/**
 * @file
 */

function townsquare_bootstrap_css_alter(&$css) {
  foreach ($css as $file => $settings) {
    // Remove jQuery UI's theming, which messes up forms
    if (strripos($file, 'date/date_api/date.css') !== FALSE) {
      unset($css[$file]);
    }
  }
}

function townsquare_bootstrap_menu_link(array $variables) {
/**
 * Implements theme_menu_link().
 *
 * Note: This isn't always called when rendering menus. Confusing!
 */
  $element = $variables['element'];
  $element['#attributes']['id'] = 'menu-link-'. drupal_clean_css_identifier($element['#original_link']['href']);
  
  $sub_menu = '';

  if ($element['#below']) {
    $sub_menu = drupal_render($element['#below']);
  }
  $element['#localized_options']['html'] = TRUE;
  $output = l($element['#title'], $element['#href'], $element['#localized_options']);
  return '<li' . drupal_attributes($element['#attributes']) . '>' . $output . $sub_menu . "</li>\n";
}

/**
 * Implements theme_preprocess_page().
 */
function townsquare_bootstrap_preprocess_page(&$vars) {
  global $user;
  $vars['primary_local_tasks'] = menu_primary_local_tasks();
  $vars['secondary_local_tasks'] = menu_secondary_local_tasks();

  // The following menu stuff is lame
  foreach ($vars['main_menu'] as $item => $options) {
    $vars['main_menu'][$item]['html'] = TRUE;
    $vars['main_menu'][$item]['attributes']['id'] = 'menu-link-'. drupal_clean_css_identifier($options['href']);
  }

  $admin_menu = menu_tree_all_data('management');
  $children = array_pop($admin_menu);
  if ($children) {
    foreach ($children['below'] as $key => $value) {
      $children['below'][$key]['below'] = array();
    }
    $vars['admin_menu'] = menu_tree_output($children['below']);
  }

  // Add user picture if logged in
  if ($user->uid) {
    $vars['user_name'] = check_plain($user->name);
    if (!empty($user->picture)) {
      if (is_numeric($user->picture)) {
        $user->picture = file_load($user->picture);
      }
      if (!empty($user->picture->uri)) {
        $filepath = $user->picture->uri;
      }
    }
    elseif (variable_get('user_picture_default', '')) {
      $filepath = variable_get('user_picture_default', '');
    }
    if (isset($filepath)) {
      $alt = t("@user's picture", array('@user' => format_username($user)));
      if (module_exists('image') && file_valid_uri($filepath) && $style = variable_get('user_picture_style', '')) {
        $vars['user_picture'] = theme('image_style', array('style_name' => $style, 'path' => $filepath, 'alt' => $alt, 'title' => $alt));
      }
      else {
        $vars['user_picture'] = theme('image', array('path' => $filepath, 'alt' => $alt, 'title' => $alt));
      }
    }
    else {
      $vars['user_picture'] = '<i class="icon-user"></i>';
    }
  }
  // Otherwise, present the login form
  else {
    unset($vars['secondary_menu']);
    $vars['login'] = drupal_get_form('user_login_block');
  }

  // Add Bootstrap
  $path = libraries_get_path('bootstrap');
  drupal_add_css($path .'/css/bootstrap.css');
  drupal_add_css($path .'/css/bootstrap-responsive.css');
  drupal_add_js($path .'/js/bootstrap.js');
  
  $path = libraries_get_path('font-awesome');
  drupal_add_css($path .'/css/font-awesome.css');
}

function townsquare_bootstrap_breadcrumb($variables) {
  $breadcrumb = $variables['breadcrumb'];
  array_shift($breadcrumb);
  $crumbs = '';
  // Only show breadcrumb for admin for now
  if (!empty($breadcrumb) && arg(0) == 'admin') {
    // Stolen from Zen
    $item = menu_get_item();
    if (empty($item['tab_parent'])) {
      // If we are on a non-default tab, use the tab's title.
      $title = check_plain($item['title']);
    }
    else {
      $title = drupal_get_title();
    }

    $crumbs = '<ul class="breadcrumb">';
    foreach($breadcrumb as $value) {
      $crumbs .= '<li>'. $value .'<span class="divider">/</span></li>';
    }
    $crumbs .= '<li>'. $title .'</li>';
    $crumbs .= '</ul>';
  }
  return $crumbs;
}

function townsquare_bootstrap_preprocess_table(&$variables) {
  $variables['attributes']['class'] = array('table');
}

function townsquare_bootstrap_preprocess_views_view_table(&$variables) {
  $variables['classes_array'][] = 'table';
}

function townsquare_bootstrap_form_element_label(&$variables) {
 $element = $variables['element'];
  // This is also used in the installer, pre-database setup.
  $t = get_t();

  // If title and required marker are both empty, output no label.
  if ((!isset($element['#title']) || $element['#title'] === '') && empty($element['#required'])) {
    return '';
  }

  // If the element is required, a required marker is appended to the label.
  $required = !empty($element['#required']) ? theme('form_required_marker', array('element' => $element)) : '';

  $title = filter_xss_admin($element['#title']);

  $attributes = array();
  // Style the label as class option to display inline with the element.
  if ($element['#title_display'] == 'after') {
    $attributes['class'] = 'option';
  }
  // Show label only to screen readers to avoid disruption in visual flows.
  elseif ($element['#title_display'] == 'invisible') {
    $attributes['class'] = 'element-invisible';
  }

  if (!empty($element['#id'])) {
    $attributes['for'] = $element['#id'];
  }
  if ($element['#type'] != 'radio' && $element['#type'] != 'checkbox') {
    $attributes['class'] = 'control-label';
  }

  // The leading whitespace helps visually separate fields from inline labels.
  return ' <label' . drupal_attributes($attributes) . '>' . $t('!title !required', array('!title' => $title, '!required' => $required)) . "</label>\n";
}  

function townsquare_bootstrap_form_element($variables) {
  $element = &$variables['element'];
  // This is also used in the installer, pre-database setup.
  $t = get_t();

  // This function is invoked as theme wrapper, but the rendered form element
  // may not necessarily have been processed by form_builder().
  $element += array(
    '#title_display' => 'before',
  );

  // Add element #id for #type 'item'.
  if (isset($element['#markup']) && !empty($element['#id'])) {
    $attributes['id'] = $element['#id'];
  }
  // Add element's #type and #name as class to aid with JS/CSS selectors.
  $attributes['class'] = array('form-item');
  if (!empty($element['#type'])) {
    $attributes['class'][] = 'form-type-' . strtr($element['#type'], '_', '-');
  }
  if (!empty($element['#name'])) {
    $attributes['class'][] = 'form-item-' . strtr($element['#name'], array(' ' => '-', '_' => '-', '[' => '-', ']' => ''));
  }
  // Add a class for disabled elements to facilitate cross-browser styling.
  if (!empty($element['#attributes']['disabled'])) {
    $attributes['class'][] = 'form-disabled';
  }
  $attributes['class'][] = 'control-group';
  $output = '<div' . drupal_attributes($attributes) . '>' . "\n";
  
  // If #title is not set, we don't display any label or required marker.
  if (!isset($element['#title'])) {
    $element['#title_display'] = 'none';
  }
  $prefix = '<div class="controls">';
  $prefix .= isset($element['#field_prefix']) ? '<span class="field-prefix">' . $element['#field_prefix'] . '</span> ' : '';
  $suffix = isset($element['#field_suffix']) ? ' <span class="field-suffix">' . $element['#field_suffix'] . '</span>' : '';
  $suffix .= '</div>';
  switch ($element['#title_display']) {
    case 'before':
    case 'invisible':
      $output .= ' ' . theme('form_element_label', $variables);
      $output .= ' ' . $prefix . $element['#children'] . $suffix . "\n";
      break;

    case 'after':
      $output .= ' ' . $prefix . $element['#children'] . $suffix;
      $output .= ' ' . theme('form_element_label', $variables) . "\n";
      break;

    case 'none':
    case 'attribute':
      // Output no label and no required marker, only the children.
      $output .= ' ' . $prefix . $element['#children'] . $suffix . "\n";
      break;
  }
  
  $output .= "</div>\n";

  return $output;
}

function townsquare_bootstrap_menu_tree__management($variables) {
  return '<ul class="dropdown-menu">' . $variables['tree'] . '</ul>';
}

function townsquare_bootstrap_button($variables) {
  $element = $variables['element'];
  $type = strtolower($element['#button_type']);
  switch($type){
    case 'submit':
    case 'reset':
    case 'button':
      break;
    default:
      $type = 'submit';
      break;
  }
  $element['#attributes']['type'] = $type;

  element_set_attributes($element, array('id', 'name', 'value'));

  $element['#attributes']['class'][] = 'form-' . $element['#button_type'];
  $element['#attributes']['class'][] = 'btn';
  if (!empty($element['#attributes']['disabled'])) {
    $element['#attributes']['class'][] = 'form-button-disabled';
  }
  
  switch ($element['#id']) {
    case 'edit-submit':
      $element['#value'] = '<i class="icon icon-bolt"></i> ' . $element['#value']; 
      $element['#attributes']['class'][] = 'btn-primary';
      break;
    case 'edit-submit-townsquare-volunteers':
      $element['#value'] .= ' <i class="icon icon-search"></i>';
      $element['#attributes']['class'][] = 'btn-primary';
      break;
    case 'edit-preview':
      $element['#value'] = '<i class="icon icon-eye-open"></i> ' . $element['#value']; 
      $element['#attributes']['class'][] = 'btn-info';
      break;
    case 'edit-delete':
      $element['#value'] = '<i class="icon icon-trash"></i> ' . $element['#value']; 
      $element['#attributes']['class'][] = 'btn-danger';
      break;
    case 'edit-preview-changes':
      $element['#value'] = '<i class="icon icon-columns"></i> ' . $element['#value']; 
      $element['#attributes']['class'][] = 'btn-inverse';
      break;
  }

  $title = isset($element['#button_text']) ? $element['#button_text'] : $element['#value'];

  return '<button' . drupal_attributes($element['#attributes']) . '>' . $title . '</button>';
}

function townsquare_bootstrap_date_popup($vars) {
  $element = $vars['element'];
  $attributes = !empty($element['#wrapper_attributes']) ? $element['#wrapper_attributes'] : array('class' => array());
  $attributes['class'][] = 'container-inline-date';
  // If there is no description, the floating date elements need some extra padding below them.
  $wrapper_attributes = array('class' => array('date-padding'));
  if (empty($element['date']['#description'])) {
    $wrapper_attributes['class'][] = 'clearfix';
  }
  if (empty($element['#title'])) {
    $element['#title'] = t('From:');
  }
  // Add an wrapper to mimic the way a single value field works, for ease in using #states.
  if (isset($element['#children'])) {
    $element['#children'] = '<div id="' . $element['#id'] . '" ' . drupal_attributes($wrapper_attributes) . '>' . $element['#children'] . '</div>';
  }
  return '<div ' . drupal_attributes($attributes) . '>' . theme('form_element', $element) . '</div>';
}
