import './bootstrap';

document.querySelectorAll('[data-dialog-open]').forEach((button) => {
	button.addEventListener('click', () => {
		document.getElementById(button.dataset.dialogOpen)?.showModal();
	});
});

document.querySelectorAll('[data-dialog-close]').forEach((button) => {
	button.addEventListener('click', () => button.closest('dialog')?.close());
});

document.querySelectorAll('dialog').forEach((dialog) => {
	dialog.addEventListener('click', (event) => {
		if (event.target === dialog) dialog.close();
	});
});

document.querySelectorAll('[data-confirm-delete]').forEach((form) => {
	form.addEventListener('submit', (event) => {
		if (!window.confirm('Delete this task?')) event.preventDefault();
	});
});

const taskList = document.querySelector('[data-sortable]');

if (taskList) {
	let draggedRow = null;
	let orderChanged = false;

	taskList.querySelectorAll('[data-drag-handle]').forEach((handle) => {
		handle.addEventListener('dragstart', (event) => {
			draggedRow = handle.closest('[data-task-row]');
			orderChanged = false;
			draggedRow.classList.add('is-dragging');
			event.dataTransfer.effectAllowed = 'move';
			event.dataTransfer.setData('text/plain', draggedRow.dataset.taskId);
		});

		handle.addEventListener('dragend', async () => {
			if (!draggedRow) return;

			draggedRow.classList.remove('is-dragging');
			draggedRow = null;

			if (!orderChanged) return;

			const taskIds = [...taskList.querySelectorAll('[data-task-row]')]
				.map((row) => Number(row.dataset.taskId));

			try {
				const response = await fetch(taskList.dataset.reorderUrl, {
					method: 'POST',
					headers: {
						'Accept': 'application/json',
						'Content-Type': 'application/json',
						'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
					},
					body: JSON.stringify({ project_id: Number(taskList.dataset.projectId), task_ids: taskIds }),
				});

				if (!response.ok) throw new Error('Could not save task order.');

				taskList.querySelectorAll('[data-task-row]').forEach((row, index) => {
					row.querySelector('[data-priority-value]').textContent = index + 1;
				});
			} catch {
				window.location.reload();
			}
		});
	});

	taskList.addEventListener('dragover', (event) => {
		event.preventDefault();
		const targetRow = event.target.closest('[data-task-row]');

		if (!draggedRow || !targetRow || targetRow === draggedRow) return;

		const bounds = targetRow.getBoundingClientRect();
		const insertAfter = event.clientY > bounds.top + bounds.height / 2;
		taskList.insertBefore(draggedRow, insertAfter ? targetRow.nextSibling : targetRow);
		orderChanged = true;
	});
}
