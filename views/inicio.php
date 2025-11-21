<?php
// Variables disponibles: $error, $pregunta, $progreso, $resultado, $partidaId
if (!empty($error)): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-5">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if ($resultado): ?>
    <?php partial('resultado', compact('resultado'));
    return; ?>
<?php endif; ?>

<?php if ($pregunta): ?>
    <?php partial('pregunta', compact('pregunta', 'progreso'));
    return; ?>
<?php endif; ?>

<div class="bg-white border-2 border-black p-8 max-w-md w-full text-center">
    <h1 class="text-5xl font-bold text-black mb-6">
        <i class="fas fa-crystal-ball fa-large"></i> Akineitor
    </h1>

    <div class="mb-8">
        <p class="text-lg text-black mb-4">¡Bienvenido al juego más misterioso de internet!</p>
        <p class="text-black mb-4">Piensa en cualquier personaje <strong class="text-black">real o
                ficticio de la Saga DragonBall</strong> y yo intentaré adivinarlo con solo unas preguntas.</p>
        <p class="text-gray-500 text-sm">¿Estás listo para el desafío? <i class="fas fa-target"></i></p>
    </div>

    <form action="index.php?action=comenzar" method="post" class="mb-6">
        <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
        <button
            class="bg-black hover:bg-white border-2 border-black text-white hover:text-black font-bold py-4 px-8 text-lg transition-all duration-300"
            type="submit">
            <i class="fas fa-rocket fa-icon"></i> Comenzar Partida
        </button>
    </form>

    <?php if (!empty($partidaId)): ?>
        <p class="text-gray-400 text-xs">
            <i class="fas fa-check-circle"></i> Partida lista • ID: <?= htmlspecialchars($partidaId) ?>
        </p>
    <?php endif; ?>
</div>