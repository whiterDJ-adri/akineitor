<div class="flex flex-col h-full">
    <!-- Header: Question Number & Progress -->
    <div class="grid-row bg-gray-50">
        <div class="grid-col-span-4 grid-cell flex items-center justify-center border-r-black">
            <div class="text-4xl font-black text-[var(--db-orange)]">
                #<?= str_pad((string) ($progreso['paso'] ?? 1), 2, '0', STR_PAD_LEFT) ?>
            </div>
        </div>
        <div class="grid-col-span-8 grid-cell flex flex-col justify-center">
            <?php if (isset($confianza)): ?>
                <?php $pc = (int) round(((float) $confianza) * 100); ?>
                <div class="flex justify-between text-xs font-mono uppercase mb-2">
                    <span>Confianza del Análisis</span>
                    <span><?= $pc ?>%</span>
                </div>
                <div class="progress-container-grid">
                    <div class="progress-fill-grid" style="width: <?= $pc ?>%;"></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main: Question Text -->
    <div class="flex-grow flex items-center justify-center p-8 sm:p-16 text-center">
        <h2 class="text-3xl sm:text-5xl font-black uppercase leading-tight tracking-tight">
            <?= htmlspecialchars($pregunta['texto'] ?? '...', ENT_QUOTES, 'UTF-8'); ?>
        </h2>
    </div>

    <!-- Footer: Answers -->
    <form action="index.php?action=responder" method="post" class="grid-row border-t-black mt-auto">
        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
        <input type="hidden" name="preguntaId"
            value="<?= htmlspecialchars($pregunta['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

        <button class="grid-col-span-4 btn-block-style hover:bg-[var(--db-orange)] hover:text-white border-r-black"
            type="submit" name="respuesta" value="si">
            SÍ
        </button>
        <button class="grid-col-span-4 btn-block-style hover:bg-gray-800 hover:text-white border-r-black" type="submit"
            name="respuesta" value="ns">
            NO SÉ
        </button>
        <button class="grid-col-span-4 btn-block-style hover:bg-red-600 hover:text-white" type="submit" name="respuesta"
            value="no">
            NO
        </button>
    </form>

    <!-- Reset Link -->
    <div class="text-center p-2 border-t-black">
        <form action="index.php?action=reiniciar" method="post">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
            <button class="text-xs text-gray-400 hover:text-black font-mono uppercase" type="submit">
                [ REINICIAR SISTEMA ]
            </button>
        </form>
    </div>
</div>