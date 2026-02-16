/**
 * Project Management Module - Admin JavaScript
 * 
 * @package EL_Core
 * @subpackage Modules\Project_Management
 */

(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        initProjectModal();
        initTaskModal();
        initPhaseAccordion();
        initTaskCheckboxes();
        initKanbanDragDrop();
    });
    
    /**
     * Initialize Project Modal
     */
    function initProjectModal() {
        // Open add project modal
        $('#add-project-btn, #add-first-project-btn').on('click', function() {
            resetProjectForm();
            $('#project-modal-title').text('Create New Project');
            $('#project-action').val('add_project');
            $('#project-id').val('');
            $('#el-project-modal').addClass('active').fadeIn();
        });
        
        // Open edit project modal
        $('.edit-project-btn').on('click', function() {
            const projectId = $(this).data('project-id');
            loadProjectData(projectId);
        });
        
        // Close modal
        $('#el-project-modal .el-modal-close').on('click', closeProjectModal);
        
        // Close on overlay click
        $('#el-project-modal').on('click', function(e) {
            if (e.target === this) {
                closeProjectModal();
            }
        });
    }
    
    /**
     * Load project data for editing
     */
    function loadProjectData(projectId) {
        $.ajax({
            url: elCore.ajaxUrl,
            type: 'POST',
            data: {
                action: 'el_core_ajax_get_project',
                nonce: elCore.nonce,
                project_id: projectId
            },
            success: function(response) {
                if (response.success && response.data.project) {
                    const project = response.data.project;
                    
                    $('#project-modal-title').text('Edit Project');
                    $('#project-action').val('edit_project');
                    $('#project-id').val(project.id);
                    $('#project-name').val(project.name);
                    $('#project-company').val(project.company_id || '');
                    $('#project-contact').val(project.contact_id || '');
                    $('#project-type').val(project.type);
                    $('#project-status').val(project.status);
                    $('#project-staging-url').val(project.staging_url || '');
                    $('#project-live-url').val(project.live_url || '');
                    $('#project-start-date').val(project.start_date || '');
                    $('#project-target-date').val(project.target_launch_date || '');
                    $('#project-notes').val(project.notes || '');
                    
                    $('#el-project-modal').addClass('active').fadeIn();
                } else {
                    alert('Failed to load project data');
                }
            },
            error: function() {
                alert('Error loading project data');
            }
        });
    }
    
    /**
     * Reset project form
     */
    function resetProjectForm() {
        $('#project-form')[0].reset();
        $('#project-id').val('');
    }
    
    /**
     * Close project modal
     */
    window.closeProjectModal = function() {
        $('#el-project-modal').removeClass('active').fadeOut();
    };
    
    /**
     * Initialize Task Modal
     */
    function initTaskModal() {
        // Open add task modal
        $('.add-task-btn').on('click', function() {
            const phaseId = $(this).data('phase-id');
            const projectId = $(this).data('project-id');
            
            resetTaskForm();
            $('#task-modal-title').text('Add Task');
            $('#task-action').val('add_task');
            $('#task-id').val('');
            $('#task-phase-id').val(phaseId);
            $('#task-project-id').val(projectId);
            $('#el-task-modal').addClass('active').fadeIn();
        });
        
        // Open edit task modal
        $('.edit-task-btn').on('click', function() {
            const taskId = $(this).data('task-id');
            loadTaskData(taskId);
        });
        
        // Close modal
        $('#el-task-modal .el-modal-close').on('click', closeTaskModal);
        
        // Close on overlay click
        $('#el-task-modal').on('click', function(e) {
            if (e.target === this) {
                closeTaskModal();
            }
        });
    }
    
    /**
     * Load task data for editing
     */
    function loadTaskData(taskId) {
        $.ajax({
            url: elCore.ajaxUrl,
            type: 'POST',
            data: {
                action: 'el_core_ajax_get_task',
                nonce: elCore.nonce,
                task_id: taskId
            },
            success: function(response) {
                if (response.success && response.data.task) {
                    const task = response.data.task;
                    
                    $('#task-modal-title').text('Edit Task');
                    $('#task-action').val('edit_task');
                    $('#task-id').val(task.id);
                    $('#task-phase-id').val(task.phase_id);
                    $('#task-project-id').val(task.project_id || '');
                    $('#task-title').val(task.title);
                    $('#task-description').val(task.description || '');
                    $('#task-assigned-to').val(task.assigned_to || '');
                    $('#task-priority').val(task.priority);
                    $('#task-due-date').val(task.due_date || '');
                    $('#task-status').val(task.status || 'todo');
                    
                    $('#el-task-modal').addClass('active').fadeIn();
                } else {
                    alert('Failed to load task data');
                }
            },
            error: function() {
                alert('Error loading task data');
            }
        });
    }
    
    /**
     * Reset task form
     */
    function resetTaskForm() {
        $('#task-form')[0].reset();
        $('#task-id').val('');
    }
    
    /**
     * Close task modal
     */
    window.closeTaskModal = function() {
        $('#el-task-modal').removeClass('active').fadeOut();
    };
    
    /**
     * Initialize Phase Accordion
     */
    function initPhaseAccordion() {
        // Toggle phase content
        window.togglePhase = function(phaseId) {
            const content = $('#phase-' + phaseId);
            const arrow = content.prev('.el-phase-header').find('.el-accordion-arrow');
            
            if (content.is(':visible')) {
                content.slideUp();
                arrow.removeClass('active');
            } else {
                content.slideDown();
                arrow.addClass('active');
            }
        };
    }
    
    /**
     * Initialize Task Checkboxes
     */
    function initTaskCheckboxes() {
        $('.el-task-checkbox').on('change', function() {
            const taskId = $(this).data('task-id');
            const projectId = $(this).data('project-id');
            const isChecked = $(this).is(':checked');
            
            toggleTaskStatus(taskId, projectId, isChecked);
        });
    }
    
    /**
     * Toggle task status (complete/incomplete)
     */
    function toggleTaskStatus(taskId, projectId, isCompleted) {
        $.ajax({
            url: elCore.ajaxUrl,
            type: 'POST',
            data: {
                action: 'el_core_ajax_toggle_task_status',
                nonce: elCore.nonce,
                task_id: taskId,
                project_id: projectId
            },
            success: function(response) {
                if (response.success) {
                    // Update UI
                    const taskItem = $('.el-task-checkbox[data-task-id="' + taskId + '"]')
                        .closest('.el-task-item');
                    const taskTitle = taskItem.find('.el-task-title');
                    
                    if (isCompleted) {
                        taskItem.addClass('el-task-completed');
                        taskTitle.addClass('completed');
                    } else {
                        taskItem.removeClass('el-task-completed');
                        taskTitle.removeClass('completed');
                    }
                    
                    // Optionally reload page to update progress bars
                    // location.reload();
                } else {
                    alert('Failed to update task status');
                    // Revert checkbox
                    $('.el-task-checkbox[data-task-id="' + taskId + '"]')
                        .prop('checked', !isCompleted);
                }
            },
            error: function() {
                alert('Error updating task status');
                // Revert checkbox
                $('.el-task-checkbox[data-task-id="' + taskId + '"]')
                    .prop('checked', !isCompleted);
            }
        });
    }
    
    /**
     * Initialize Kanban Drag and Drop
     */
    function initKanbanDragDrop() {
        if ($('.el-kanban-board').length === 0) {
            return;
        }
        
        // Make cards draggable
        $('.el-kanban-card').attr('draggable', 'true');
        
        // Drag start
        $('.el-kanban-card').on('dragstart', function(e) {
            $(this).addClass('dragging');
            e.originalEvent.dataTransfer.effectAllowed = 'move';
            e.originalEvent.dataTransfer.setData('text/html', $(this).html());
            e.originalEvent.dataTransfer.setData('project-id', $(this).data('project-id'));
        });
        
        // Drag end
        $('.el-kanban-card').on('dragend', function() {
            $(this).removeClass('dragging');
        });
        
        // Drag over
        $('.el-kanban-cards').on('dragover', function(e) {
            e.preventDefault();
            e.originalEvent.dataTransfer.dropEffect = 'move';
            $(this).addClass('drag-over');
        });
        
        // Drag leave
        $('.el-kanban-cards').on('dragleave', function() {
            $(this).removeClass('drag-over');
        });
        
        // Drop
        $('.el-kanban-cards').on('drop', function(e) {
            e.preventDefault();
            $(this).removeClass('drag-over');
            
            const projectId = e.originalEvent.dataTransfer.getData('project-id');
            const newStatus = $(this).data('status');
            const card = $('.el-kanban-card[data-project-id="' + projectId + '"]');
            
            // Move card visually
            card.appendTo(this);
            
            // Update status via AJAX
            updateProjectStatus(projectId, newStatus);
        });
    }
    
    /**
     * Update project status (for kanban drag-and-drop)
     */
    function updateProjectStatus(projectId, newStatus) {
        $.ajax({
            url: elCore.ajaxUrl,
            type: 'POST',
            data: {
                action: 'el_core_ajax_update_project_status',
                nonce: elCore.nonce,
                project_id: projectId,
                status: newStatus
            },
            success: function(response) {
                if (response.success) {
                    // Update kanban counts
                    $('.el-kanban-column').each(function() {
                        const status = $(this).data('status');
                        const count = $(this).find('.el-kanban-card').length;
                        $(this).find('.el-kanban-count').text(count);
                    });
                } else {
                    alert('Failed to update project status');
                    location.reload();
                }
            },
            error: function() {
                alert('Error updating project status');
                location.reload();
            }
        });
    }
    
    /**
     * Escape key closes modals
     */
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            if ($('#el-project-modal').hasClass('active')) {
                closeProjectModal();
            }
            if ($('#el-task-modal').hasClass('active')) {
                closeTaskModal();
            }
        }
    });
    
})(jQuery);
