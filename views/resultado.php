<?php
$personaje = $resultado['personaje'] ?? ['nombre' => 'Desconocido', 'imagenUrl' => null];
$conf = isset($resultado['confianza']) ? (float) $resultado['confianza'] : 0.0;
$porcentaje = (int) round($conf);
$fallbackImage = 'assets/img/Shen_Long_Artwork.png';
$imagenMostrar = !empty($personaje['imagenUrl']) ? $personaje['imagenUrl'] : $fallbackImage;
?>

<div class="flex flex-col h-full">
    <!-- Header: Status -->
    <div class="grid-row bg-black text-white">
        <div class="grid-col-span-12 p-4 text-center font-mono text-sm uppercase tracking-widest">
            Objetivo Identificado
        </div>
    </div>

    <!-- Main Content: Image & Data -->
    <div class="grid-row flex-grow">
        <!-- Image Column -->
        <div class="grid-col-span-6 grid-cell flex items-center justify-center bg-gray-50 relative overflow-hidden p-0">
            <div class="absolute inset-0 bg-[var(--db-yellow)] opacity-10"
                style="background-image: radial-gradient(circle, #000 1px, transparent 1px); background-size: 20px 20px;">
            </div>
            <img src="<?= htmlspecialchars($imagenMostrar, ENT_QUOTES, 'UTF-8') ?>"
                alt="<?= htmlspecialchars($personaje['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                class="character-img-grid relative z-10">
        </div>

        <!-- Info Column -->
        <div class="grid-col-span-6 grid-cell flex flex-col justify-center">
            <div class="mb-6">
                <div class="badge-grid bg-[var(--db-orange)] text-black">Coincidencia Encontrada</div>
                <h1 class="text-5xl font-black uppercase leading-none mb-2 tracking-tighter">
                    <?= htmlspecialchars($personaje['nombre'], ENT_QUOTES, 'UTF-8') ?>
                </h1>
                <div class="font-mono text-xs text-gray-400 uppercase tracking-widest">
                    Análisis de Nivel de Poder Completo
                </div>
            </div>

            <div class="mb-8">
                <div class="flex justify-between text-xs font-mono uppercase mb-1">
                    <span>Probabilidad</span>
                    <span><?= ($porcentaje == 0 && $conf > 0) ? '< 1' : $porcentaje ?>%</span>
                </div>
                <div class="progress-container-grid h-4">
                    <div class="progress-fill-grid bg-black" style="width: <?= $porcentaje ?>%;"></div>
                </div>
            </div>

            <p class="text-lg font-bold italic leading-tight">
                <?php if ($porcentaje >= 90): ?>
                    "NO HAY DUDA ALGUNA. ES ESTE GUERRERO."
                <?php elseif ($porcentaje >= 70): ?>
                    "MIS ESCÁNERES INDICAN UNA ALTA PROBABILIDAD."
                <?php else: ?>
                    "LOS DATOS NO SON CLAROS. ¿ES CORRECTO?"
                <?php endif; ?>
            </p>
        </div>
    </div>

    <!-- Actions Footer -->
    <div class="grid-row mt-auto">
        <form action="index.php?action=reiniciar" method="POST" class="grid-col-span-4">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
            <button class="btn-block-style w-full h-full hover:bg-[var(--db-orange)] hover:text-white border-r-black"
                type="submit">
                JUGAR DE NUEVO
            </button>
        </form>

        <form action="index.php?action=continuar" method="POST" class="grid-col-span-4">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
            <button class="btn-block-style w-full h-full hover:bg-black hover:text-white border-r-black" type="submit">
                SEGUIR JUGANDO
            </button>
        </form>

        <form action="index.php?action=reiniciar" method="POST" class="grid-col-span-4">
            <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
            <button class="btn-block-style w-full h-full hover:bg-gray-800 hover:text-white" type="submit">
                INICIO
            </button>
        </form>
    </div>

    <!-- Correction Section -->
    <details class="border-t-black group">
        <summary class="p-4 text-center cursor-pointer text-xs font-mono uppercase hover:bg-gray-100 transition-colors">
            [ ¿IDENTIFICACIÓN INCORRECTA? HAZ CLIC PARA CORREGIR ]
        </summary>

        <div class="p-8 bg-gray-50 border-t-black">
            <?php $alternativos = $resultado['personajes_alternativos'] ?? []; ?>
            <?php if (!empty($alternativos)): ?>
                <form action="index.php?action=corregir" method="POST" class="mb-8">
                    <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                    <label class="text-xs font-bold uppercase block mb-2">EN REALIDAD ERA:</label>
                    <div class="flex gap-0">
                        <select name="personajeId" class="input-grid border-r-0 w-full">
                            <?php foreach ($alternativos as $alt): ?>
                                <option value="<?= (int) $alt['id'] ?>">
                                    <?= htmlspecialchars($alt['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn-block-style bg-black text-white border-black px-6"
                            type="submit">CORREGIR</button>
                    </div>
                </form>
            <?php endif; ?>

            <form action="index.php?action=sugerir" method="POST">
                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                <label class="text-xs font-bold uppercase block mb-2">SUGERIR NUEVO GUERRERO:</label>
                <div class="flex gap-0">
                    <input type="text" name="nombre" placeholder="NOMBRE..." class="input-grid border-r-0 w-full">
                    <button class="btn-block-style bg-gray-200 border-black px-6" type="submit">ENVIAR</button>
                </div>
            </form>
        </div>
    </details>
</div>