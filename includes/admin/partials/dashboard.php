<div class="gptwp-dashboard-wrapper">
    
    <!-- CABECERA PRINCIPAL -->
    <div class="gptwp-header-row">
        <div>
            <h2 class="gptwp-main-title">Centro de Mando Académico</h2>
            <p class="gptwp-subtitle">Gestión de accesos, datos personales y seguridad.</p>
        </div>
        <div class="gptwp-search-wrapper">
            <span class="dashicons dashicons-search gptwp-search-icon"></span>
            <input type="text" id="crm_search_input" placeholder="Buscar por nombre o email..." autocomplete="off">
        </div>
    </div>

    <!-- TABLA DE USUARIOS (CON WRAPPER RESPONSIVO) -->
    <div class="gptwp-card-table">
        <div class="gptwp-table-responsive">
            <table class="gptwp-crm-table">
                <thead>
                    <tr>
                        <th width="60"></th>
                        <th>Estudiante</th>
                        <th>Email</th>
                        <th>Estado Cuenta</th>
                        <th style="text-align:right;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="crm_results_body">
                    <tr>
                        <td colspan="5" style="text-align:center; padding:40px; color:#666;">
                            <span class="dashicons dashicons-update spin"></span> Cargando base de datos...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- PAGINACIÓN -->
        <div class="gptwp-pagination-row">
            <span id="gptwp_paging_info" class="gptwp-paging-info">Cargando...</span>
            <div class="gptwp-paging-controls">
                <button id="btn_prev_page" class="btn-paging" disabled>&lsaquo; Anterior</button>
                <button id="btn_next_page" class="btn-paging" disabled>Siguiente &rsaquo;</button>
            </div>
        </div>
    </div>

    <!-- NOTIFICACIÓN TOAST PERSONALIZADA -->
    <div id="gptwp_toast" class="gptwp-toast"></div>

</div>

<!-- MODAL DE GESTIÓN AVANZADA -->
<div id="gptwp_permission_modal" class="gptwp-modal-overlay">
    <div class="gptwp-modal-content">
        
        <div class="gptwp-modal-header">
            <h3 style="margin:0; color:#fff;">Gestión de Usuario</h3>
            <button class="gptwp-modal-close">&times;</button>
        </div>

        <div id="gptwp_modal_body" class="gptwp-modal-body-scroll">
            <!-- Aquí se carga el contenido vía AJAX -->
        </div>

        <div class="gptwp-modal-footer">
            <div class="modal-footer-note">
                * Los permisos de Lecciones/Temas requieren "Guardar". Datos y Bloqueos son inmediatos al pulsar su botón.
            </div>
            <button id="btn_save_permissions" class="gptwp-btn-save">
                GUARDAR PERMISOS DE CONTENIDO
            </button>
        </div>
    </div>
</div>
