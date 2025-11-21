<?php
$personaje = $resultado['personaje'] ?? ['nombre' => 'Desconocido', 'imagenUrl' => null];
$conf = isset($resultado['confianza']) ? (float) $resultado['confianza'] : 0.0;
$porcentaje = (int) round($conf * 100);
?>

<div class="bg-white border-2 border-black p-8 max-w-md w-full text-center">
    <h1 class="text-4xl font-bold text-black mb-6">
        <i class="fas fa-trophy fa-icon"></i> ¡Resultado!
    </h1>

    <div class="bg-white border-2 border-black p-6 mb-6">
        <div class="mb-4">
            <span class="font-mono text-black font-semibold">
                &gt; <i class="fas fa-crystal-ball fa-icon"></i> ¡Creo que ya lo tengo!<span
                    class="cursor-blink"></span>
            </span>
        </div>

        <?php if (!empty($personaje['imagenUrl'])): ?>
            <div class="mb-5">
                <img src="<?= htmlspecialchars($personaje['imagenUrl'], ENT_QUOTES, 'UTF-8') ?>"
                    alt="<?= htmlspecialchars($personaje['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                    class="max-w-[150px] mx-auto border-2 border-black">
            </div>
        <?php else: ?>
            <div class="text-6xl mb-5 text-black">
                <i class="fas fa-user-circle"></i>
            </div>
        <?php endif; ?>

        <div class="text-3xl font-bold mb-4 text-black font-mono">
            <?= htmlspecialchars($personaje['nombre'], ENT_QUOTES, 'UTF-8') ?>
        </div>

        <div class="text-xl font-semibold mb-4 text-black font-mono">
            <i class="fas fa-percentage fa-icon"></i> Confianza: <?= $porcentaje ?>%
        </div>

        <?php if ($porcentaje >= 90): ?>
            <p class="text-sm text-black font-mono">
                <i class="fas fa-bullseye fa-icon"></i> ¡Estoy muy seguro de que es correcto!
            </p>
        <?php elseif ($porcentaje >= 70): ?>
            <p class="text-sm text-black font-mono">
                <i class="fas fa-thumbs-up fa-icon"></i> Tengo una buena sensación sobre esto
            </p>
        <?php else: ?>
            <p class="text-sm text-black font-mono">
                <i class="fas fa-question-circle fa-icon"></i> No estoy completamente seguro, pero es mi mejor suposición
            </p>
        <?php endif; ?>
    </div>

    <div class="flex flex-wrap gap-4 justify-center">
        <form action="index.php?action=reiniciar" method="post">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
            <button
                class="bg-white hover:bg-black border-2 border-black text-black hover:text-white font-semibold px-6 py-3 transition-all duration-300"
                type="submit">
                <i class="fas fa-redo fa-icon"></i> Jugar de nuevo
            </button>
        </form>

        <form action="index.php?action=continuar" method="post">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
            <button
                class="bg-white hover:bg-black border-2 border-black text-black hover:text-white font-semibold px-6 py-3 transition-all duration-300"
                type="submit">
                <i class="fas fa-forward fa-icon"></i> Seguir jugando
            </button>
        </form>

        <form action="index.php" method="GET">
            <button
                class="bg-white hover:bg-black border-2 border-black text-black hover:text-white font-semibold px-6 py-3 transition-all duration-300"
                type="submit">
                <i class="fas fa-home fa-icon"></i> Inicio
            </button>
        </form>
    </div>

    <div class="bg-white border-2 border-black p-6 mt-6">
        <div class="text-left mb-4">
            <span class="font-mono text-black font-semibold">&gt; ¿Fue incorrecto? Corrige o sugiere</span>
        </div>
        <?php $alternativos = $resultado['personajes_alternativos'] ?? []; ?>
        <?php if (!empty($alternativos)): ?>
            <form action="index.php?action=corregir" method="post" class="mb-4">
                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                <label class="block text-sm font-mono text-black mb-2">Elegir personaje correcto</label>
                <select name="personajeId" class="border-2 border-black px-3 py-2 w-full">
                    <?php foreach ($alternativos as $alt): ?>
                        <option value="<?= (int)$alt['id'] ?>">
                            <?= htmlspecialchars($alt['nombre'] . ' (' . (int)round(($alt['probabilidad'] ?? 0) * 100) . '%)', ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="mt-3 bg-white hover:bg-black border-2 border-black text-black hover:text-white font-semibold px-4 py-2" type="submit">
                    Aplicar corrección
                </button>
            </form>
        <?php endif; ?>
        <form action="index.php?action=sugerir" method="post">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
            <label class="block text-sm font-mono text-black mb-2">Sugerir personaje nuevo</label>
            <input type="text" name="nombre" placeholder="Nombre" class="border-2 border-black px-3 py-2 w-full mb-2">
            <input type="text" name="imagen_url" placeholder="URL de imagen (opcional)" class="border-2 border-black px-3 py-2 w-full mb-2">
            <textarea name="descripcion" placeholder="Descripción (opcional)" class="border-2 border-black px-3 py-2 w-full mb-2"></textarea>
            <button class="bg-white hover:bg-black border-2 border-black text-black hover:text-white font-semibold px-4 py-2" type="submit">
                Sugerir personaje
            </button>
        </form>
    </div>
</div>