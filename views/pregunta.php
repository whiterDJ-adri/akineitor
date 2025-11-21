<div class="bg-white border-2 border-black p-8 max-w-md w-full text-center">
    <h1 class="text-4xl font-bold text-black mb-6">
        <i class="fas fa-question-circle fa-icon"></i> Pregunta
    </h1>

    <?php if (!empty($progreso)): ?>
        <div class="mb-6">
            <div class="border-t-2 border-black mb-2"></div>
            <p class="text-sm text-black font-mono">
                [ <?= (int) $progreso['paso'] ?> / <?= (int) $progreso['total'] ?> ]
            </p>
            <div class="border-t-2 border-black mt-2"></div>
        </div>
    <?php endif; ?>
    <?php if (!empty($confianza)): ?>
        <?php $pc = (int)round(((float)$confianza) * 100); ?>
        <div class="mb-4">
            <p class="text-sm text-black font-mono"><i class="fas fa-percentage fa-icon"></i> Progreso de adivinación: <?= $pc ?>%</p>
        </div>
    <?php endif; ?>

    <div class="bg-white border-2 border-black p-6 mb-6">
        <div class="mb-4 text-left">
            <div class="font-mono text-black text-lg">
                <span class="text-black">&gt; </span>
                <span id="question-text" class="inline">
                    <?= htmlspecialchars($pregunta['texto'] ?? '...', ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <span class="cursor-blink"></span>
            </div>
        </div>

        <form action="index.php?action=responder" method="post">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
            <input type="hidden" name="preguntaId"
                value="<?= htmlspecialchars($pregunta['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

            <div class="flex flex-wrap gap-4 justify-center mb-6">
                <button
                    class="bg-white hover:bg-black border-2 border-black text-black hover:text-white font-semibold px-6 py-3 min-w-[120px] transition-all duration-300"
                    type="submit" name="respuesta" value="si">
                    <i class="fas fa-check fa-icon"></i> Sí
                </button>
                <button
                    class="bg-white hover:bg-black border-2 border-black text-black hover:text-white font-semibold px-6 py-3 min-w-[120px] transition-all duration-300"
                    type="submit" name="respuesta" value="no">
                    <i class="fas fa-times fa-icon"></i> No
                </button>
                <button
                    class="bg-white hover:bg-black border-2 border-black text-black hover:text-white font-semibold px-6 py-3 min-w-[120px] transition-all duration-300"
                    type="submit" name="respuesta" value="ns">
                    <i class="fas fa-question fa-icon"></i> No sé
                </button>
            </div>
        </form>
    </div>

    <form action="index.php?action=reiniciar" method="post">
        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
        <button
            class="bg-white hover:bg-black border-2 border-black text-black hover:text-white font-medium px-4 py-2 text-sm transition-all duration-300"
            type="submit">
            <i class="fas fa-redo fa-icon"></i> Reiniciar partida
        </button>
    </form>
</div>
</div>