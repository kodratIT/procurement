function initializeWorkflowCanvas(canvas) {
    if (canvas.dataset.initialized === 'true') {
        return;
    }

    const inner = canvas.querySelector('.workflow-inner');
    const stage = canvas.querySelector('.workflow-stage');
    const nodes = [...canvas.querySelectorAll('.workflow-node')];
    const edgeElements = [...canvas.querySelectorAll('[data-edge-id]')];

    if (!inner || !stage || nodes.length === 0) {
        return;
    }

    canvas.dataset.initialized = 'true';

    const worldWidth = Number(canvas.dataset.worldWidth);
    const worldHeight = Number(canvas.dataset.worldHeight);
    const storageKey = `workflow-layout:${canvas.dataset.workflowId}:v${canvas.dataset.workflowVersion}`;
    let scale = 1;

    const nodeById = (id) => nodes.find((node) => node.dataset.nodeId === id);
    const nodePosition = (node) => ({
        x: Number.parseFloat(node.style.left),
        y: Number.parseFloat(node.style.top),
    });
    const limit = (value, minimum, maximum) => Math.min(maximum, Math.max(minimum, value));

    function drawEdges() {
        edgeElements.forEach((edge) => {
            const source = nodeById(edge.dataset.source);
            const target = nodeById(edge.dataset.target);

            if (!source || !target) {
                return;
            }

            const sourcePosition = nodePosition(source);
            const targetPosition = nodePosition(target);
            const handleRatio = edge.dataset.sourceHandle === 'true' ? 0.32 : (edge.dataset.sourceHandle === 'false' ? 0.68 : 0.5);
            const startX = sourcePosition.x + source.offsetWidth;
            const startY = sourcePosition.y + (source.offsetHeight * handleRatio);
            const endX = targetPosition.x;
            const endY = targetPosition.y + (target.offsetHeight / 2);
            const controlOffset = Math.max(72, Math.abs(endX - startX) * 0.45);
            const path = edge.querySelector('.workflow-edge-path');
            const label = edge.querySelector('.workflow-edge-label');

            if (!path) {
                return;
            }

            path.setAttribute('d', `M ${startX} ${startY} C ${startX + controlOffset} ${startY}, ${endX - controlOffset} ${endY}, ${endX} ${endY}`);

            if (label) {
                label.setAttribute('transform', `translate(${((startX + endX) / 2) - 21} ${((startY + endY) / 2) - 10})`);
            }
        });
    }

    function updateScale(nextScale, keepCenter = true) {
        const previousScale = scale;
        const centerX = (canvas.scrollLeft + (canvas.clientWidth / 2)) / previousScale;
        const centerY = (canvas.scrollTop + (canvas.clientHeight / 2)) / previousScale;
        scale = limit(nextScale, 0.45, 1.5);
        inner.dataset.scale = String(scale);
        inner.style.transform = `scale(${scale})`;
        stage.style.width = `${worldWidth * scale}px`;
        stage.style.height = `${worldHeight * scale}px`;

        if (keepCenter) {
            canvas.scrollLeft = (centerX * scale) - (canvas.clientWidth / 2);
            canvas.scrollTop = (centerY * scale) - (canvas.clientHeight / 2);
        }
    }

    function fitWorkflow() {
        const fittedScale = Math.min(1, (canvas.clientWidth - 72) / worldWidth, (canvas.clientHeight - 72) / worldHeight);
        updateScale(Math.max(0.45, fittedScale), false);
        canvas.scrollLeft = Math.max(0, ((worldWidth * scale) - canvas.clientWidth) / 2);
        canvas.scrollTop = Math.max(0, ((worldHeight * scale) - canvas.clientHeight) / 2);
    }

    function savedPositions() {
        try {
            return JSON.parse(localStorage.getItem(storageKey) || '{}');
        } catch {
            return {};
        }
    }

    function savePositions() {
        const positions = Object.fromEntries(nodes.map((node) => [node.dataset.nodeId, nodePosition(node)]));

        try {
            localStorage.setItem(storageKey, JSON.stringify(positions));
        } catch {
            // The canvas remains usable when browser storage is unavailable.
        }
    }

    function applyPositions(positions) {
        nodes.forEach((node) => {
            const position = positions[node.dataset.nodeId];
            node.style.left = `${position?.x ?? Number(node.dataset.defaultX)}px`;
            node.style.top = `${position?.y ?? Number(node.dataset.defaultY)}px`;
        });

        requestAnimationFrame(drawEdges);
    }

    nodes.forEach((node) => {
        let dragStart = null;

        node.addEventListener('pointerdown', (event) => {
            if (event.button !== 0) {
                return;
            }

            const position = nodePosition(node);
            dragStart = { pointerX: event.clientX, pointerY: event.clientY, ...position };
            node.setPointerCapture(event.pointerId);
            node.style.zIndex = '20';
            node.classList.add('drop-shadow-xl');
            event.stopPropagation();
            event.preventDefault();
        });

        node.addEventListener('pointermove', (event) => {
            if (!dragStart) {
                return;
            }

            const x = limit(dragStart.x + ((event.clientX - dragStart.pointerX) / scale), 24, worldWidth - node.offsetWidth - 24);
            const y = limit(dragStart.y + ((event.clientY - dragStart.pointerY) / scale), 48, worldHeight - node.offsetHeight - 48);
            node.style.left = `${x}px`;
            node.style.top = `${y}px`;
            drawEdges();
        });

        const finishDrag = (event) => {
            if (!dragStart) {
                return;
            }

            const position = nodePosition(node);
            node.style.left = `${Math.round(position.x / 12) * 12}px`;
            node.style.top = `${Math.round(position.y / 12) * 12}px`;
            node.style.zIndex = '';
            node.classList.remove('drop-shadow-xl');
            dragStart = null;
            node.releasePointerCapture?.(event.pointerId);
            drawEdges();
            savePositions();
        };

        node.addEventListener('pointerup', finishDrag);
        node.addEventListener('pointercancel', finishDrag);
    });

    let panStart = null;

    canvas.addEventListener('pointerdown', (event) => {
        if (event.button !== 0 || event.target.closest('.workflow-node, .workflow-toolbar')) {
            return;
        }

        panStart = {
            pointerX: event.clientX,
            pointerY: event.clientY,
            scrollLeft: canvas.scrollLeft,
            scrollTop: canvas.scrollTop,
        };
        canvas.setPointerCapture(event.pointerId);
        canvas.style.cursor = 'grabbing';
        event.preventDefault();
    });

    canvas.addEventListener('pointermove', (event) => {
        if (!panStart) {
            return;
        }

        canvas.scrollLeft = panStart.scrollLeft - (event.clientX - panStart.pointerX);
        canvas.scrollTop = panStart.scrollTop - (event.clientY - panStart.pointerY);
    });

    const finishPan = (event) => {
        if (!panStart) {
            return;
        }

        panStart = null;
        canvas.style.cursor = 'grab';
        canvas.releasePointerCapture?.(event.pointerId);
    };

    canvas.addEventListener('pointerup', finishPan);
    canvas.addEventListener('pointercancel', finishPan);
    canvas.addEventListener('wheel', (event) => {
        if (!event.ctrlKey && !event.metaKey) {
            return;
        }

        event.preventDefault();
        updateScale(scale + (event.deltaY < 0 ? 0.1 : -0.1));
    }, { passive: false });

    canvas.querySelectorAll('[data-workflow-control]').forEach((control) => {
        control.addEventListener('click', () => {
            const action = control.dataset.workflowControl;

            if (action === 'zoom-in') {
                updateScale(scale + 0.1);
            } else if (action === 'zoom-out') {
                updateScale(scale - 0.1);
            } else if (action === 'fit') {
                fitWorkflow();
            } else if (action === 'reset') {
                try {
                    localStorage.removeItem(storageKey);
                } catch {
                    // Reset still works when browser storage is unavailable.
                }

                applyPositions({});
                fitWorkflow();
            }
        });
    });

    applyPositions(savedPositions());
    requestAnimationFrame(fitWorkflow);

    const resizeObserver = new ResizeObserver(drawEdges);
    resizeObserver.observe(inner);
}

function initializeWorkflowCanvases(root = document) {
    if (root instanceof Element && root.matches('.workflow-canvas')) {
        initializeWorkflowCanvas(root);
    }

    root.querySelectorAll?.('.workflow-canvas').forEach(initializeWorkflowCanvas);
}

initializeWorkflowCanvases();
document.addEventListener('livewire:navigated', () => initializeWorkflowCanvases());

const workflowCanvasObserver = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
            if (node instanceof Element) {
                initializeWorkflowCanvases(node);
            }
        });
    });
});

workflowCanvasObserver.observe(document.documentElement, { childList: true, subtree: true });
