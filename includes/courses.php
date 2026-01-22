<?php
// ==============================================================================
// MÓDULO: CURSOS Y LISTADOS
// ==============================================================================

// ==============================================================================
// SHORTCODE: LISTA DE CURSOS (DISEÑO COMPACTO Y ELEGANTE "FACEPILE")
// ==============================================================================
add_shortcode('ld_lista_cursos', 'ld_lista_cursos_fn');
function ld_lista_cursos_fn() {

    $cursos = get_posts([
        'post_type'      => 'sfwd-courses',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC'
    ]);

    if (!$cursos) return "<p style='text-align:center; color:#666;'>No hay cursos disponibles actualmente.</p>";

    ob_start();

    echo '<div class="ldwp-grid">';

    foreach($cursos as $curso):
        $curso_id   = $curso->ID;
        $usuario_id = get_current_user_id();
        $url        = get_permalink($curso_id);
        $tiene_acceso = sfwd_lms_has_access($curso_id, $usuario_id);

        // --- 1. BOTONES Y BADGES ---
        if ($tiene_acceso) {
            $badge = 'En Curso';
            $badge_class = 'ldwp-badge-enrolled';
            $btn_text = 'Continuar';
            $btn_class = 'ldwp-btn-primary'; 
            $btn_link = $url; 
        } else {
            $badge = 'Premium'; 
            $badge_class = 'ldwp-badge-sales';
            $btn_text = 'Ver más';
            $btn_class = 'ldwp-btn-secondary'; 
            $btn_link = $url; 
        }

        $thumb = get_the_post_thumbnail($curso_id, 'large') ?: '<div class="ldwp-placeholder"></div>';
        
        $porcentaje = 0;
        if ($tiene_acceso && function_exists('learndash_course_progress')) {
            $progress = learndash_course_progress(['user_id' => $usuario_id, 'course_id' => $curso_id, 'array' => true]);
            $porcentaje = intval($progress['percentage'] ?? 0);
        }

        // --- 2. LÓGICA MENTORES (RECOLECCIÓN DE DATOS) ---
        $mentores_list = []; 
        
        if (function_exists('get_field')) {
            $mentores_repeater = get_field('mentores', $curso_id);

            if ($mentores_repeater && is_array($mentores_repeater) && !empty($mentores_repeater)) {
                foreach ($mentores_repeater as $fila_mentor) {
                    $acf_nombre = $fila_mentor['nombre_del_mentor'] ?? '';
                    $acf_foto   = $fila_mentor['portada_mentor'] ?? '';
                    
                    if (!empty($acf_nombre)) {
                        $this_avatar = '';
                        // Procesar foto
                        $img_url = '';
                        if ($acf_foto) {
                            if (is_array($acf_foto) && isset($acf_foto['sizes']['thumbnail'])) {
                                $img_url = $acf_foto['sizes']['thumbnail'];
                            } elseif (is_array($acf_foto) && isset($acf_foto['url'])) {
                                $img_url = $acf_foto['url'];
                            } elseif (is_numeric($acf_foto)) {
                                $img = wp_get_attachment_image_src($acf_foto, [80, 80]);
                                if($img) $img_url = $img[0];
                            } elseif (is_string($acf_foto)) {
                                $img_url = $acf_foto;
                            }
                        }
                        
                        // Generar tag IMG limpio sin wrappers extraños para el facepile
                        if ($img_url) {
                            $this_avatar = '<img src="'.esc_url($img_url).'" alt="'.esc_attr($acf_nombre).'" class="ldwp-avatar-img">';
                        } else {
                            $this_avatar = '<div class="ldwp-avatar-initial">'.strtoupper(substr($acf_nombre, 0, 1)).'</div>';
                        }

                        $mentores_list[] = [
                            'name'   => $acf_nombre,
                            'avatar' => $this_avatar
                        ];
                    }
                }
            } 
            
            // Fallback campo antiguo
            if (empty($mentores_list)) {
                 $mentor_obj = get_field('mentor', $curso_id);
                 if ($mentor_obj) {
                    $m_name = ''; $m_url = '';
                    if (is_a($mentor_obj, 'WP_User')) {
                        $m_name = $mentor_obj->display_name;
                        $m_url = get_avatar_url($mentor_obj->ID);
                    } elseif (is_string($mentor_obj)) {
                        $m_name = $mentor_obj;
                    }
                    
                    $av = $m_url ? '<img src="'.$m_url.'" class="ldwp-avatar-img">' : '<div class="ldwp-avatar-initial">'.strtoupper(substr($m_name, 0, 1)).'</div>';
                    if($m_name) $mentores_list[] = ['name' => $m_name, 'avatar' => $av];
                 }
            }
        }

        // Fallback Autor
        if (empty($mentores_list)) {
            $author_id = $curso->post_author;
            $m_name    = get_the_author_meta('display_name', $author_id);
            $m_url     = get_avatar_url($author_id);
            $av = '<img src="'.$m_url.'" class="ldwp-avatar-img">';
            $mentores_list[] = ['name' => $m_name, 'avatar' => $av];
        }

        // --- 3. GENERAR HTML COMPACTO (FACEPILE) ---
        $mentores_html = '';
        if (!empty($mentores_list)) {
            $avatars_html = '';
            $nombres_array = [];
            
            foreach ($mentores_list as $index => $mentor) {
                // Solo mostramos max 4 fotos para no saturar si hay muchos
                if ($index < 4) {
                    $avatars_html .= '<div class="ldwp-avatar-item" style="z-index:'.(10-$index).'">'.$mentor['avatar'].'</div>';
                }
                $nombres_array[] = $mentor['name'];
            }
            
            // Si hay mas de 4, añadimos un indicador visual
            if (count($mentores_list) > 4) {
                $restantes = count($mentores_list) - 4;
                $avatars_html .= '<div class="ldwp-avatar-item ldwp-avatar-more" style="z-index:0">+'. $restantes .'</div>';
            }

            $nombres_str = implode(', ', $nombres_array);
            // Truncar nombres si es muy largo
            if (strlen($nombres_str) > 50) {
                $nombres_str = substr($nombres_str, 0, 47) . '...';
            }

            $mentores_html = '<div class="ldwp-mentores-wrapper">
                                <div class="ldwp-mentores-stack">'.$avatars_html.'</div>
                                <div class="ldwp-mentores-text">
                                    <span class="ldwp-label-mini">Mentores</span>
                                    <span class="ldwp-names-list">'.esc_html($nombres_str).'</span>
                                </div>
                              </div>';
        }
        ?>

        <div class="ldwp-card <?php echo $tiene_acceso ? 'access-granted' : 'access-denied'; ?>">
            <div class="ldwp-thumb">
                <span class="ldwp-badge <?php echo $badge_class; ?>"><?php echo $badge; ?></span>
                <?php echo $thumb; ?>
                <div class="ldwp-thumb-overlay"></div>
            </div>

            <div class="ldwp-content">
                <h3 class="ldwp-titulo"><?php echo esc_html($curso->post_title); ?></h3>

                <!-- LISTA DE MENTORES COMPACTA -->
                <?php echo $mentores_html; ?>

                <?php if ($tiene_acceso): ?>
                    <div class="ldwp-progress-wrapper">
                        <div class="ldwp-progress-bar" style="width: <?php echo $porcentaje; ?>%;"></div>
                    </div>
                    <p class="ldwp-progress-text"><?php echo $porcentaje; ?>% completado</p>
                <?php else: ?>
                    <p class="ldwp-desc-short">Contenido exclusivo</p>
                <?php endif; ?>

                <div class="ldwp-footer">
                    <a href="<?php echo esc_url($btn_link); ?>"
                       class="ldwp-btn <?php echo $btn_class; ?> ldwp-ingresar-curso"
                       data-curso="<?php echo esc_attr($curso_id); ?>">
                       <?php echo $btn_text; ?>
                    </a>
                </div>
            </div>
        </div>

    <?php endforeach; ?>
    </div>

    <!-- Estilos movidos a assets/css/frontend.css -->
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll(".ldwp-ingresar-curso").forEach(btn => {
            btn.addEventListener("click", function () {
                if(this.classList.contains('ldwp-btn-primary')) {
                    document.cookie = "gptwp_curso_actual=" + this.dataset.curso + "; path=/; max-age=" + (60 * 60 * 48) + ";";
                }
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}


// Crear taxonomía personalizada para cursos
function registrar_taxonomia_categoria_cursos() {

    register_taxonomy(
        'curso_categoria',
        'sfwd-courses',
        array(
            'label' => 'Categorías del Curso',
            'rewrite' => array('slug' => 'categoria-curso'),
            'hierarchical' => false,
            'show_admin_column' => true,
        )
    );
}
add_action( 'init', 'registrar_taxonomia_categoria_cursos' );


/* ==========================================================================
   SHORTCODE 1: MIS CURSOS (GRID FLUIDO + ESTILO BRAND)
   Uso: [ld_mis_cursos]
   ========================================================================== */
add_shortcode('ld_mis_cursos', 'ld_mis_cursos_fn');
function ld_mis_cursos_fn() {

    if (!is_user_logged_in()) {
        return "<div class='ldwp-alert-simple'>Debes iniciar sesión para ver los cursos.</div>";
    }

    $usuario_id = get_current_user_id();

    $all_courses = get_posts([
        'post_type'      => 'sfwd-courses',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC'
    ]);

    if (empty($all_courses)) {
        return "<div class='ldwp-alert-simple'>No hay cursos disponibles actualmente.</div>";
    }

    ob_start();
    echo '<div class="ldwp-fluid-grid">';

    foreach ($all_courses as $curso_post):
        $curso_id = $curso_post->ID;
        $url      = get_permalink($curso_id);
        $thumb    = get_the_post_thumbnail($curso_id, 'large') ?: '<div class="ldwp-placeholder"></div>';
        
        // Verificación de acceso
        $has_access = sfwd_lms_has_access($curso_id, $usuario_id);

        // Auto-reparación de acceso (Legacy logic)
        if (!$has_access) {
            $access_meta = get_user_meta($usuario_id, 'course_' . $curso_id . '_access_from', true);
            $our_mark = get_user_meta($usuario_id, 'enabled_modules_' . $curso_id, true);
            if (!empty($access_meta) || !empty($our_mark)) {
                if (function_exists('ld_update_course_access')) {
                    ld_update_course_access($usuario_id, $curso_id, false);
                    $has_access = true; 
                }
            }
        }

        if ($has_access) {
            $badge = 'EN CURSO';
            $badge_class = 'ldwp-badge-enrolled';
            $btn_text = 'CONTINUAR';
            $btn_class = 'ldwp-btn-primary';
        } else {
            $badge = 'DISPONIBLE';
            $badge_class = 'ldwp-badge-info';
            $btn_text = 'MÁS INFORMACIÓN';
            $btn_class = 'ldwp-btn-info';
        }

        $porcentaje = 0;
        if ($has_access && function_exists('learndash_course_progress')) {
            $progress = learndash_course_progress([
                'user_id'   => $usuario_id,
                'course_id' => $curso_id,
                'array'     => true
            ]);
            $porcentaje = intval($progress['percentage'] ?? 0);
        }

        // Mentores
        $mentores_html = '';
        if (function_exists('get_field')) {
            $mentores_repeater = get_field('mentores', $curso_id);
            if ($mentores_repeater && is_array($mentores_repeater)) {
                $avatars_html = '';
                foreach (array_slice($mentores_repeater, 0, 3) as $index => $fila) {
                    $img = $fila['portada_mentor'];
                    $img_url = is_array($img) ? $img['sizes']['thumbnail'] : $img;
                    if($img_url) $avatars_html .= '<div class="ldwp-avatar-stack-item" style="z-index:'.(10-$index).'"><img src="'.$img_url.'"></div>';
                }
                if($avatars_html) $mentores_html = '<div class="ldwp-mentores-row">'.$avatars_html.'</div>';
            }
        }
        ?>
        <div class="ldwp-card-fluid">
            <div class="ldwp-thumb-wrapper">
                <span class="ldwp-status-badge <?php echo $badge_class; ?>"><?php echo $badge; ?></span>
                <?php echo $thumb; ?>
                <div class="ldwp-overlay-gradient"></div>
            </div>
            <div class="ldwp-card-body">
                <h3 class="ldwp-card-title"><?php echo esc_html($curso_post->post_title); ?></h3>
                <?php echo $mentores_html; ?>
                
                <?php if ($has_access): ?>
                    <div class="ldwp-prog-container">
                        <div class="ldwp-prog-bar" style="width: <?php echo $porcentaje; ?>%;"></div>
                    </div>
                    <p class="ldwp-meta-text"><?php echo $porcentaje; ?>% COMPLETADO</p>
                <?php else: ?>
                    <p class="ldwp-meta-text locked">ACCESO RESTRINGIDO</p>
                <?php endif; ?>

                <div class="ldwp-card-footer">
                    <a href="<?php echo esc_url($url); ?>" class="ldwp-action-btn <?php echo $btn_class; ?>">
                        <?php echo $btn_text; ?>
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
    
    <!-- Estilos movidos a assets/css/frontend.css -->
    <?php
    return ob_get_clean();
}
