<?php
// ==============================================================================
// MÓDULO: LOGROS Y NOTIFICACIONES
// ==============================================================================

// ==============================================================================
// === MÓDULO 1: SISTEMA DE NOTIFICACIONES ===
// ==============================================================================

// 1. CREACIÓN DE LA TABLA DE NOTIFICACIONES
add_action('init', function() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'gptwp_notifications';
    
    if($wpdb->get_var("SHOW TABLES LIKE '$tabla'") != $tabla) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $tabla (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            type varchar(50) NOT NULL, /* 'course', 'update', 'achievement', 'system' */
            title varchar(255) NOT NULL,
            message text NOT NULL,
            link varchar(255) DEFAULT '' NOT NULL,
            is_read tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
});

// 2. FUNCIÓN PARA CREAR NOTIFICACIONES (OPTIMIZADA BULK INSERT)
if (!function_exists('gptwp_crear_notificacion')) {
    function gptwp_crear_notificacion($user_ids, $title, $message, $type = 'system', $link = '#') {
        global $wpdb;
        $tabla = $wpdb->prefix . 'gptwp_notifications';
        
        if (!is_array($user_ids)) {
            $user_ids = [$user_ids];
        }

        // Procesar en lotes de 500 para eficiencia y seguridad
        $chunks = array_chunk($user_ids, 500);

        foreach ($chunks as $chunk) {
            $values = [];
            $placeholders = [];
            
            foreach ($chunk as $uid) {
                // Preparamos los valores para una sola consulta SQL masiva
                array_push($values, $uid, $title, $message, $type, $link);
                $placeholders[] = "(%d, %s, %s, %s, %s)";
            }
            
            if(!empty($placeholders)) {
                $query = "INSERT INTO $tabla (user_id, title, message, type, link) VALUES " . implode(', ', $placeholders);
                $wpdb->query($wpdb->prepare($query, $values));
            }
        }
    }
}

// 3. AUTOMATIZACIÓN: NUEVO CURSO LEARNDASH
add_action('publish_sfwd-courses', function($post_id, $post) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (get_post_meta($post_id, '_gptwp_notified_new', true)) return;

    // Obtener TODOS los estudiantes (Sin límite, gracias a la optimización anterior)
    $users = get_users(['fields' => 'ID']); 
    
    gptwp_crear_notificacion(
        $users,
        '🎓 Nuevo Curso Disponible',
        'Acabamos de lanzar: ' . $post->post_title . '. ¡Échale un vistazo!',
        'course',
        get_permalink($post_id)
    );

    update_post_meta($post_id, '_gptwp_notified_new', 'yes');
}, 10, 2);

// 4. SHORTCODE CAMPANA: [area_notificaciones]
add_shortcode('area_notificaciones', function() {
    if (!is_user_logged_in()) return '';
    
    wp_enqueue_style('dashicons');
    wp_enqueue_script('jquery');

    global $wpdb;
    $user_id = get_current_user_id();
    $tabla = $wpdb->prefix . 'gptwp_notifications';
    
    $notifs = $wpdb->get_results("SELECT * FROM $tabla WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 15");
    $count = count($notifs);
    $active_class = $count > 0 ? 'has-items' : '';

    ob_start();
    ?>
    <div class="gptwp-bell-wrapper">
        <div class="gptwp-bell-btn <?php echo $active_class; ?>" id="gptwpBellBtn">
            <span class="dashicons dashicons-bell"></span>
            <?php if($count > 0): ?>
                <span class="gptwp-badge"><?php echo $count; ?></span>
            <?php endif; ?>
        </div>

        <div class="gptwp-notif-dropdown" id="gptwpNotifDropdown">
            <div class="notif-head">
                <span class="head-title">Notificaciones</span>
                <?php if($count > 0): ?>
                    <button class="head-clear" onclick="gptwpClearAll()">Limpiar todo</button>
                <?php endif; ?>
            </div>
            
            <div class="notif-body">
                <?php if($count == 0): ?>
                    <div class="notif-empty">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <p>No tienes notificaciones nuevas.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($notifs as $n): 
                        $icon = 'dashicons-info'; $color = '#aaa';
                        if($n->type == 'achievement') { $icon = 'dashicons-awards'; $color = '#F9B137'; }
                        if($n->type == 'course') { $icon = 'dashicons-welcome-learn-more'; $color = '#4dff88'; }
                    ?>
                    <div class="notif-item" id="notif_item_<?php echo $n->id; ?>">
                        <div class="notif-icon" style="color: <?php echo $color; ?>; border-color: <?php echo $color; ?>;">
                            <span class="dashicons <?php echo $icon; ?>"></span>
                        </div>
                        <div class="notif-content">
                            <a href="<?php echo esc_url($n->link); ?>" class="notif-link">
                                <span class="notif-title"><?php echo esc_html($n->title); ?></span>
                                <span class="notif-msg"><?php echo esc_html($n->message); ?></span>
                            </a>
                            <span class="notif-time"><?php echo human_time_diff(strtotime($n->created_at), current_time('timestamp')); ?> atrás</span>
                        </div>
                        <button class="notif-close" onclick="gptwpDeleteNotif(<?php echo $n->id; ?>)">&times;</button>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Estilos movidos a assets/css/frontend.css -->
    <script>
    jQuery(document).ready(function($) {
        const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
        $('#gptwpBellBtn').click(function(e) { e.stopPropagation(); $('#gptwpNotifDropdown').toggleClass('show'); $(this).toggleClass('active'); });
        $(document).click(function(e) { if (!$(e.target).closest('.gptwp-bell-wrapper').length) { $('#gptwpNotifDropdown').removeClass('show'); $('#gptwpBellBtn').removeClass('active'); } });
        window.gptwpDeleteNotif = function(id) { $.post(ajaxUrl, { action: 'gptwp_notif_delete', id: id }, function(res) { if(res.success) { $('#notif_item_' + id).slideUp(function() { $(this).remove(); checkEmpty(); }); updateCount(-1); } }); };
        
        // BORRADO SIN CONFIRMACIÓN (RÁPIDO)
        window.gptwpClearAll = function() { 
            $.post(ajaxUrl, { action: 'gptwp_notif_clear_all' }, function(res) { 
                if(res.success) { 
                    $('.notif-body').html('<div class="notif-empty"><span class="dashicons dashicons-yes-alt"></span><p>Limpio</p></div>'); 
                    $('.gptwp-badge').remove(); 
                    $('.head-clear').remove(); 
                } 
            }); 
        };
        
        function updateCount(c) { let b=$('.gptwp-badge'), cur=parseInt(b.text())||0, n=cur+c; if(n<=0) b.remove(); else b.text(n); }
        function checkEmpty() { if($('.notif-item').length===0) $('.notif-body').html('<div class="notif-empty"><span class="dashicons dashicons-yes-alt"></span><p>Limpio</p></div>'); }
    });
    </script>
    <?php
    return ob_get_clean();
});

// AJAX NOTIFICACIONES
add_action('wp_ajax_gptwp_notif_delete', function() {
    $id = intval($_POST['id']); $uid = get_current_user_id(); global $wpdb;
    $wpdb->delete($wpdb->prefix.'gptwp_notifications', ['id'=>$id, 'user_id'=>$uid]); wp_send_json_success();
});
add_action('wp_ajax_gptwp_notif_clear_all', function() {
    $uid = get_current_user_id(); global $wpdb;
    $wpdb->delete($wpdb->prefix.'gptwp_notifications', ['user_id'=>$uid]); wp_send_json_success();
});


// ==============================================================================
// === MÓDULO 2: SISTEMA DE LOGROS & NOVEDADES (CONECTADO) ===
// ==============================================================================

// --- 1. INICIALIZACIÓN DE BASE DE DATOS ---
add_action('init', function() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'gptwp_logros';
    
    $existe = $wpdb->get_var("SHOW TABLES LIKE '$tabla'") == $tabla;
    
    if(!$existe || !$wpdb->get_results("SHOW COLUMNS FROM `$tabla` LIKE 'assigned_user_id'")) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $tabla (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            course_id bigint(20) UNSIGNED NOT NULL,
            assigned_user_id bigint(20) UNSIGNED DEFAULT 0,
            message text NOT NULL,
            image_url varchar(255) DEFAULT '' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
});

// --- 2. SHORTCODE ADMINISTRADOR: PUBLICAR Y GESTIONAR ---
add_shortcode('admin_crear_logro', function() {
    if (!current_user_can('edit_posts')) return '';
    
    wp_enqueue_style('dashicons');
    wp_enqueue_script('jquery'); 

    $courses = get_posts(['post_type' => 'sfwd-courses', 'numberposts' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC']);

    ob_start();
    ?>
    <div class="gptwp-logros-wrapper">
        <h2 class="gptwp-title">Gestor de Logros y Novedades</h2>
        
        <div class="gptwp-layout">
            
            <!-- FORMULARIO DE CREACIÓN -->
            <div class="gptwp-card form-card">
                <h3><span class="dashicons dashicons-plus-alt2"></span> Nuevo Logro</h3>
                <form id="gptwp_logro_form" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="gptwp_save_logro_full">
                    <input type="hidden" name="id" value="0">

                    <!-- Curso -->
                    <div class="form-group">
                        <label>1. Curso (Requerido)</label>
                        <select name="course_id" id="create_course_id" required>
                            <option value="">-- Selecciona --</option>
                            <?php foreach($courses as $c): ?>
                                <option value="<?php echo $c->ID; ?>"><?php echo esc_html($c->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Buscador Estudiante -->
                    <div class="form-group">
                        <label>2. Asignar a Estudiante (Opcional)</label>
                        <div class="user-search-wrapper">
                            <input type="text" class="user-search-input" placeholder="Escribe nombre o email..." autocomplete="off">
                            <input type="hidden" name="assigned_user_id" class="selected_user_id" value="0">
                            <div class="user-search-results"></div>
                            <div class="selected-user-display" style="display:none;">
                                <span class="sel-name">Usuario Seleccionado</span>
                                <span class="sel-remove dashicons dashicons-no-alt"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Mensaje -->
                    <div class="form-group">
                        <label>3. Mensaje</label>
                        <textarea name="message" rows="4" placeholder="Describe el logro..." required></textarea>
                    </div>

                    <!-- Imagen -->
                    <div class="form-group">
                        <label>4. Imagen</label>
                        <div class="image-upload-ui">
                            <input type="file" name="image" id="logro_img_input" accept="image/*" style="display:none;">
                            <button type="button" class="btn-select-img" onclick="document.getElementById('logro_img_input').click()">
                                <span class="dashicons dashicons-format-image"></span> Elegir Imagen
                            </button>
                            <div id="img_preview_box" class="img-preview-box" style="display:none;">
                                <img id="img_preview_src" src="">
                                <span class="remove-img-btn" onclick="clearImage('#logro_img_input', '#img_preview_box')">&times;</span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-publish">PUBLICAR</button>
                </form>
            </div>

            <!-- HISTORIAL CON FILTROS -->
            <div class="gptwp-card history-card">
                <h3><span class="dashicons dashicons-list-view"></span> Historial</h3>
                
                <!-- BARRA DE FILTROS -->
                <div class="history-filters">
                    <input type="text" id="filter_search" placeholder="Buscar..." class="filter-input">
                    <select id="filter_course" class="filter-select">
                        <option value="">Todos los cursos</option>
                        <?php foreach($courses as $c): ?>
                            <option value="<?php echo $c->ID; ?>"><?php echo esc_html($c->post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="gptwp_admin_list_container" class="history-list">
                    <p style="text-align:center; color:#666;">Cargando...</p>
                </div>
            </div>
        </div>

        <!-- MODAL DE EDICIÓN ADMIN -->
        <div id="admin_edit_modal" class="gptwp-fe-modal">
            <div class="gptwp-fe-modal-content admin-modal-size">
                <div class="fe-header">
                    <h3>Editar Logro</h3>
                    <span class="fe-close" onclick="closeAdminModal()">&times;</span>
                </div>
                <div class="fe-body-scroll">
                    <form id="gptwp_edit_form" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="gptwp_save_logro_full">
                        <input type="hidden" name="id" id="edit_logro_id">

                        <div class="form-group">
                            <label>Curso</label>
                            <select name="course_id" id="edit_course_id" required>
                                <?php foreach($courses as $c): ?>
                                    <option value="<?php echo $c->ID; ?>"><?php echo esc_html($c->post_title); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Estudiante Asignado</label>
                            <div class="user-search-wrapper">
                                <input type="text" class="user-search-input" id="edit_user_search" placeholder="Buscar usuario..." autocomplete="off">
                                <input type="hidden" name="assigned_user_id" id="edit_assigned_user_id" class="selected_user_id">
                                <div class="user-search-results"></div>
                                <div class="selected-user-display" id="edit_user_display" style="display:none;">
                                    <span class="sel-name"></span>
                                    <span class="sel-remove dashicons dashicons-no-alt"></span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Mensaje</label>
                            <textarea name="message" id="edit_message" rows="6"></textarea>
                        </div>

                        <div class="form-group">
                            <label>Cambiar Imagen</label>
                            <div class="image-upload-ui">
                                <input type="file" name="image" id="edit_img_input" accept="image/*" style="display:none;">
                                <button type="button" class="btn-select-img" onclick="document.getElementById('edit_img_input').click()">
                                    <span class="dashicons dashicons-format-image"></span> Cambiar Imagen
                                </button>
                                <div id="edit_preview_box" class="img-preview-box" style="display:none;">
                                    <img id="edit_preview_src" src="">
                                    <span class="remove-img-btn" onclick="clearImage('#edit_img_input', '#edit_preview_box')">&times;</span>
                                </div>
                            </div>
                        </div>

                        <div class="fe-actions">
                            <button type="button" class="btn-fe-cancel" onclick="closeAdminModal()">Cancelar</button>
                            <button type="submit" class="btn-fe-save">Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL DE CONFIRMACIÓN DE BORRADO -->
        <div id="gptwp_confirm_modal" class="gptwp-fe-modal">
            <div class="gptwp-fe-modal-content confirm-modal-size">
                <div class="confirm-icon"><span class="dashicons dashicons-trash"></span></div>
                <h3>¿Estás seguro?</h3>
                <p>Esta acción eliminará el logro permanentemente. No se puede deshacer.</p>
                <div class="fe-actions center-actions">
                    <button class="btn-fe-cancel" onclick="closeConfirmModal()">Cancelar</button>
                    <button id="btn_confirm_delete" class="btn-fe-delete">Sí, Eliminar</button>
                </div>
            </div>
        </div>

        <div id="gptwp_toast" class="gptwp-toast"></div>
    </div>

    <!-- Estilos movidos a assets/css/frontend.css -->
    <script>
    function clearImage(inputSel, boxSel) {
        jQuery(inputSel).val("");
        jQuery(boxSel).hide().find('img').attr('src', "");
    }

    jQuery(document).ready(function($) {
        const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
        let deleteTargetId = 0; // ID to delete

        // 1. Imágenes
        function setupImagePreview(inputSel, imgSel, boxSel) {
            $(inputSel).change(function() {
                if(this.files && this.files[0]) {
                    var reader = new FileReader();
                    reader.onload = function(e) { $(imgSel).attr('src', e.target.result); $(boxSel).show(); }
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }
        setupImagePreview('#logro_img_input', '#img_preview_src', '#img_preview_box');
        setupImagePreview('#edit_img_input', '#edit_preview_src', '#edit_preview_box');

        // 2. Buscador Usuarios
        function setupUserSearch(wrapper) {
            let input = wrapper.find('.user-search-input');
            let results = wrapper.find('.user-search-results');
            let hidden = wrapper.find('.selected_user_id');
            let display = wrapper.find('.selected-user-display');
            let timer;

            input.on('keyup', function() {
                clearTimeout(timer);
                let term = $(this).val();
                if(term.length < 2) { results.hide(); return; }
                timer = setTimeout(function() {
                    $.post(ajaxUrl, { action: 'gptwp_search_users', term: term }, function(res) {
                        if(res.success && res.data.length) {
                            let html = '';
                            res.data.forEach(u => {
                                html += `<div class="user-result-item" data-id="${u.id}" data-name="${u.name}"><img src="${u.avatar}"> ${u.name}</div>`;
                            });
                            results.html(html).show();
                        } else { results.html('<div style="padding:10px;color:#666">No encontrado</div>').show(); }
                    });
                }, 400);
            });

            results.on('click', '.user-result-item', function() {
                hidden.val($(this).data('id'));
                display.find('.sel-name').text($(this).data('name'));
                display.show(); input.hide(); results.hide();
            });

            display.find('.sel-remove').click(function() {
                hidden.val(0); display.hide(); input.val('').show().focus();
            });
        }
        setupUserSearch($('#gptwp_logro_form .user-search-wrapper'));
        setupUserSearch($('#gptwp_edit_form .user-search-wrapper'));

        // 3. Submit Formularios
        function handleFormSubmit(formId, btnSel) {
            $(formId).on('submit', function(e) {
                e.preventDefault();
                let formData = new FormData(this);
                let btn = $(this).find(btnSel);
                btn.prop('disabled', true).text('Guardando...');

                $.ajax({
                    url: ajaxUrl, type: 'POST', data: formData, contentType: false, processData: false,
                    success: function(res) {
                        btn.prop('disabled', false).text(btnSel==='.btn-publish'?'PUBLICAR':'Guardar Cambios');
                        if(res.success) {
                            showToast('Guardado correctamente');
                            loadAdminLogros();
                            if(formId === '#gptwp_logro_form') {
                                $(formId)[0].reset(); clearImage('#logro_img_input', '#img_preview_box');
                                $(formId).find('.sel-remove').click();
                            } else {
                                closeAdminModal();
                            }
                        } else { showToast('Error: ' + res.data); }
                    }
                });
            });
        }
        handleFormSubmit('#gptwp_logro_form', '.btn-publish');
        handleFormSubmit('#gptwp_edit_form', '.btn-fe-save');

        // 4. Acciones Lista + FILTROS
        loadAdminLogros();
        
        // Eventos Filtros
        $('#filter_search, #filter_course').on('input change', function() {
            // Debounce pequeño
            clearTimeout(window.filterTimeout);
            window.filterTimeout = setTimeout(loadAdminLogros, 400);
        });

        function loadAdminLogros() {
            let search = $('#filter_search').val();
            let course = $('#filter_course').val();

            $.post(ajaxUrl, { 
                action: 'gptwp_get_logros_admin_only',
                search: search,
                course: course
            }, function(res) {
                if(res.success) $('#gptwp_admin_list_container').html(res.data);
                else $('#gptwp_admin_list_container').html('<p style="text-align:center; color:#666;">Sin resultados.</p>');
            });
        }

        $(document).on('click', '.btn-edit', function() {
            let id = $(this).data('id');
            $.post(ajaxUrl, { action: 'gptwp_get_logro_details', id: id }, function(res) {
                if(res.success) {
                    let d = res.data;
                    $('#edit_logro_id').val(d.id);
                    $('#edit_course_id').val(d.course_id);
                    $('#edit_message').val(d.message);
                    clearImage('#edit_img_input', '#edit_preview_box');
                    if(d.image_url) { $('#edit_preview_src').attr('src', d.image_url); $('#edit_preview_box').show(); }
                    
                    let uWrap = $('#gptwp_edit_form .user-search-wrapper');
                    if(d.assigned_user_id > 0) {
                        $('#edit_assigned_user_id').val(d.assigned_user_id);
                        uWrap.find('.sel-name').text(d.user_name);
                        uWrap.find('.selected-user-display').show();
                        $('#edit_user_search').hide();
                    } else {
                        uWrap.find('.selected-user-display').hide();
                        $('#edit_user_search').show().val('');
                        $('#edit_assigned_user_id').val(0);
                    }
                    $('#admin_edit_modal').css('display', 'flex');
                }
            });
        });

        // BORRADO CON MODAL PERSONALIZADO
        $(document).on('click', '.btn-delete', function() {
            deleteTargetId = $(this).data('id');
            $('#gptwp_confirm_modal').css('display', 'flex');
        });

        $('#btn_confirm_delete').click(function() {
            if(deleteTargetId > 0) {
                $.post(ajaxUrl, { action: 'gptwp_delete_logro_only', id: deleteTargetId }, function(res){ 
                    loadAdminLogros(); 
                    closeConfirmModal();
                    showToast('Logro eliminado.');
                });
            }
        });

        window.closeAdminModal = function() { $('#admin_edit_modal').hide(); }
        window.closeConfirmModal = function() { $('#gptwp_confirm_modal').hide(); deleteTargetId=0; }
        function showToast(msg) { $('#gptwp_toast').text(msg).addClass('show'); setTimeout(()=>$('#gptwp_toast').removeClass('show'),3000); }
    });
    </script>
    <?php
    return ob_get_clean();
});

// --- 3. SHORTCODE ESTUDIANTE: VER MIS LOGROS ---
add_shortcode('ver_mis_logros', function() {
    if (!is_user_logged_in()) return '';

    wp_enqueue_style('dashicons'); 
    wp_enqueue_script('jquery');   

    global $wpdb;
    $user_id = get_current_user_id();
    $is_admin = current_user_can('edit_posts');
    
    // Obtener cursos
    if (!function_exists('learndash_user_get_enrolled_courses')) return '';
    $enrolled = learndash_user_get_enrolled_courses($user_id);
    
    if ($is_admin) {
        $where = "1=1";
    } else {
        if (empty($enrolled)) {
            $where = "assigned_user_id = $user_id";
        } else {
            $ids_str = implode(',', array_map('intval', $enrolled));
            $where = "(course_id IN ($ids_str) OR assigned_user_id = $user_id)";
        }
    }

    $tabla = $wpdb->prefix . 'gptwp_logros';
    $logros = $wpdb->get_results("SELECT * FROM $tabla WHERE $where ORDER BY created_at DESC LIMIT 50");

    if (empty($logros)) return '<div style="text-align:center;padding:20px;color:#888;">Sin novedades.</div>';

    ob_start();
    ?>
    <div class="gptwp-student-wrapper">
        <div class="gptwp-masonry-grid">
            <?php foreach($logros as $l): 
                $user_data = ($l->assigned_user_id > 0) ? get_userdata($l->assigned_user_id) : null;
                $json_data = htmlspecialchars(json_encode([
                    'title' => get_the_title($l->course_id),
                    'message' => wpautop($l->message),
                    'date' => date('d F Y', strtotime($l->created_at)),
                    'image' => $l->image_url,
                    'user_avatar' => $user_data ? get_avatar_url($user_data->ID) : '',
                    'user_name' => $user_data ? $user_data->display_name : ''
                ]), ENT_QUOTES, 'UTF-8');
            ?>
            
            <div class="logro-student-card" id="card_<?php echo $l->id; ?>">
                <div class="card-img-wrapper">
                    <?php if($l->image_url): ?>
                        <img src="<?php echo esc_url($l->image_url); ?>" class="logro-student-img">
                        <div class="img-overlay"></div>
                    <?php else: ?>
                        <div class="no-img-placeholder"></div>
                    <?php endif; ?>
                </div>
                
                <div class="logro-body">
                    <div class="logro-tags">
                        <span class="tag"><?php echo get_the_title($l->course_id); ?></span>
                        <?php if($user_data): ?>
                            <span class="tag tag-user">
                                <img src="<?php echo get_avatar_url($user_data->ID); ?>"> 
                                <?php echo esc_html($user_data->display_name); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="logro-text-preview">
                        <?php echo wp_trim_words($l->message, 20, '...'); ?>
                    </div>
                    
                    <button class="btn-view-logro" onclick='openStudentModal(<?php echo $json_data; ?>)'>Ver Logro Completo</button>
                </div>

                <?php if($is_admin): ?>
                    <div class="admin-controls">
                        <button class="btn-icon-edit" onclick="openEditModal(<?php echo $l->id; ?>)" title="Editar"><span class="dashicons dashicons-edit"></span></button>
                        <button class="btn-icon-delete" onclick="confirmDelete(<?php echo $l->id; ?>)" title="Eliminar"><span class="dashicons dashicons-trash"></span></button>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- MODAL ESTUDIANTE (VISUALIZACIÓN BLOG) -->
        <div id="student_view_modal" class="gptwp-fe-modal">
            <div class="gptwp-fe-modal-content student-modal-size">
                <div class="fe-header">
                    <h3 id="view_modal_title">Titulo</h3>
                    <span class="fe-close" onclick="closeStudentModal()">&times;</span>
                </div>
                <div class="fe-body-scroll view-body">
                    <div id="view_modal_img_container">
                        <img id="view_modal_img" src="" style="width:100%; border-radius:8px; display:none;">
                    </div>
                    <div class="view-meta-row" id="view_modal_meta"></div>
                    <div id="view_modal_content" class="view-content-text"></div>
                </div>
            </div>
        </div>

        <!-- MODAL BORRADO FRONTEND (SOLO ADMIN) -->
        <?php if($is_admin): ?>
        <div id="fe_delete_modal" class="gptwp-fe-modal">
            <div class="gptwp-fe-modal-content confirm-modal-size">
                <div class="confirm-icon"><span class="dashicons dashicons-trash"></span></div>
                <h3>¿Eliminar Logro?</h3>
                <div class="fe-actions center-actions">
                    <button class="btn-fe-cancel" onclick="closeFeDelete()">Cancelar</button>
                    <button id="btn_fe_confirm" class="btn-fe-delete">Eliminar</button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Estilos movidos a assets/css/frontend.css -->
    <script>
    function openStudentModal(data) {
        document.getElementById('view_modal_title').innerText = data.title;
        document.getElementById('view_modal_content').innerHTML = data.message;
        
        let img = document.getElementById('view_modal_img');
        if(data.image) { img.src = data.image; img.style.display = 'block'; }
        else { img.style.display = 'none'; }

        let metaHtml = `<span><span class="dashicons dashicons-calendar-alt"></span> ${data.date}</span>`;
        if(data.user_name) metaHtml += `<span class="view-user-badge"><img src="${data.user_avatar}"> ${data.user_name}</span>`;
        document.getElementById('view_modal_meta').innerHTML = metaHtml;

        document.getElementById('student_view_modal').style.display = 'flex';
    }

    function closeStudentModal() {
        document.getElementById('student_view_modal').style.display = 'none';
    }

    // --- LÓGICA ADMIN FRONTEND ---
    <?php if($is_admin): ?>
    const ajaxUrlFE = '<?php echo admin_url('admin-ajax.php'); ?>';
    let deleteIdFE = 0;

    function confirmDelete(id) {
        deleteIdFE = id;
        document.getElementById('fe_delete_modal').style.display = 'flex';
    }
    
    function closeFeDelete() {
        document.getElementById('fe_delete_modal').style.display = 'none';
        deleteIdFE = 0;
    }

    document.getElementById('btn_fe_confirm').onclick = function() {
        if(deleteIdFE > 0) {
            let card = document.getElementById('card_' + deleteIdFE);
            if(card) card.style.opacity = '0.3';
            
            jQuery.post(ajaxUrlFE, { action: 'gptwp_delete_logro_only', id: deleteIdFE }, function(res) {
                closeFeDelete();
                if(res.success && card) card.remove();
            });
        }
    };

    function openEditModal(id) {
        alert('Para editar, por favor usa el Panel de Gestión Académica.');
    }
    <?php endif; ?>
    </script>
    <?php
    return ob_get_clean();
});


// --- 4. AJAX HANDLERS ---

// Buscar Usuarios
add_action('wp_ajax_gptwp_search_users', function() {
    if (!current_user_can('edit_posts')) wp_send_json_error();
    $term = sanitize_text_field($_POST['term']);
    $users = get_users(['search' => "*{$term}*", 'number' => 10, 'search_columns' => ['user_login', 'user_email', 'display_name']]);
    $data = [];
    foreach($users as $u) $data[] = ['id'=>$u->ID, 'name'=>$u->display_name, 'email'=>$u->user_email, 'avatar'=>get_avatar_url($u->ID)];
    wp_send_json_success($data);
});

// Guardar
add_action('wp_ajax_gptwp_save_logro_full', function() {
    if (!current_user_can('edit_posts')) wp_send_json_error('Permisos');
    global $wpdb;
    
    $id = intval($_POST['id']);
    $course_id = intval($_POST['course_id']);
    $assigned_user_id = intval($_POST['assigned_user_id']);
    $message = wp_kses_post($_POST['message']);

    $image_url = null;
    if (!empty($_FILES['image']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        $attach_id = media_handle_upload('image', 0);
        if (!is_wp_error($attach_id)) $image_url = wp_get_attachment_url($attach_id);
    }

    $data = ['course_id'=>$course_id, 'assigned_user_id'=>$assigned_user_id, 'message'=>$message];
    $format = ['%d', '%d', '%s'];
    if($image_url) { $data['image_url'] = $image_url; $format[] = '%s'; }

    $tabla = $wpdb->prefix . 'gptwp_logros';
    
    if($id > 0) {
        $wpdb->update($tabla, $data, ['id'=>$id], $format, ['%d']);
    } else {
        if(!$image_url) { $data['image_url'] = ''; $format[] = '%s'; }
        $wpdb->insert($tabla, $data, $format);
        
        // --- CONECTOR DE NOTIFICACIONES ---
        if (function_exists('gptwp_crear_notificacion')) {
            $course_title = get_the_title($course_id);
            
            // A) Si es para un estudiante específico
            if ($assigned_user_id > 0) {
                gptwp_crear_notificacion(
                    $assigned_user_id,
                    '🏆 ¡Reconocimiento Personal!',
                    "Te han asignado un logro especial en el curso: $course_title",
                    'achievement'
                );
            } 
            // B) Si es para todo el curso (Obtenemos usuarios de LearnDash)
            elseif (function_exists('learndash_get_course_users_access_from_meta')) {
                $users_in_course = learndash_get_course_users_access_from_meta($course_id);
                if (!empty($users_in_course)) {
                    gptwp_crear_notificacion(
                        $users_in_course,
                        '📢 Nuevo Logro en el Muro',
                        "Se ha publicado una novedad en: $course_title",
                        'achievement'
                    );
                }
            }
        }
        // ==================================
    }
    wp_send_json_success();
});

// Detalles
add_action('wp_ajax_gptwp_get_logro_details', function() {
    if (!current_user_can('edit_posts')) wp_send_json_error();
    global $wpdb;
    $id = intval($_POST['id']);
    $logro = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}gptwp_logros WHERE id = $id");
    if($logro) {
        $logro->user_name = ($logro->assigned_user_id > 0) ? get_userdata($logro->assigned_user_id)->display_name : '';
        wp_send_json_success($logro);
    }
    wp_send_json_error();
});

// Admin List CON FILTROS
add_action('wp_ajax_gptwp_get_logros_admin_only', function() {
    if (!current_user_can('edit_posts')) wp_send_json_error();
    global $wpdb;
    
    $where = "1=1";
    if(!empty($_POST['course'])) $where .= $wpdb->prepare(" AND course_id = %d", $_POST['course']);
    if(!empty($_POST['search'])) $where .= $wpdb->prepare(" AND message LIKE %s", '%'.$wpdb->esc_like($_POST['search']).'%');

    $res = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}gptwp_logros WHERE $where ORDER BY created_at DESC LIMIT 30");
    
    ob_start();
    if(!$res) echo '<p style="text-align:center;color:#666;">Sin historial.</p>';
    foreach($res as $r) {
        $img = $r->image_url ? "<img src='{$r->image_url}' class='logro-thumb'>" : "<div class='logro-thumb' style='background:#333'></div>";
        $user_tag = ($r->assigned_user_id > 0) ? "<span style='color:#4dff88'> @".get_userdata($r->assigned_user_id)->display_name."</span>" : "";
        echo "<div class='logro-item-admin'>
            $img
            <div class='logro-content'>
                <div class='logro-meta'>".get_the_title($r->course_id)." $user_tag</div>
                <div class='logro-msg'>".esc_html($r->message)."</div>
                <div class='admin-actions'>
                    <button class='btn-mini btn-edit' data-id='{$r->id}'>Editar</button>
                    <button class='btn-mini btn-delete' data-id='{$r->id}'>Borrar</button>
                </div>
            </div>
        </div>";
    }
    wp_send_json_success(ob_get_clean());
});

// Delete
add_action('wp_ajax_gptwp_delete_logro_only', function() {
    if (!current_user_can('edit_posts')) wp_send_json_error();
    global $wpdb;
    $wpdb->delete($wpdb->prefix . 'gptwp_logros', ['id' => intval($_POST['id'])]);
    wp_send_json_success();
});
