<?php if (!empty($error)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 w-full max-w-[500px] shadow-md" role="alert">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if ($resultado): ?>
    <?php partial('resultado', compact('resultado'));
    return; ?>
<?php endif; ?>

<?php if ($pregunta): ?>
    <?php partial('pregunta', compact('pregunta', 'progreso', 'confianza'));
    return; ?>
<?php endif; ?>

<div class="grid-row flex-grow h-full">
    <!-- Left Column: Title & Info -->
    <div class="grid-col-span-6 grid-cell flex flex-col justify-center relative overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-full opacity-5 pointer-events-none">
            <i class="fas fa-dragon text-9xl absolute -left-10 -top-10"></i>
        </div>

        <div class="relative z-10">
            <div class="badge-grid mb-4">Dragon Ball Edition</div>
            <h1 class="title-main">
                THINK OF A<br>
                <span class="text-black">CHARACTER</span>
            </h1>
            <p class="text-lg font-mono mb-8 max-w-md">
                I will guess who you are thinking of using the power of the 7 Dragon Balls.
            </p>

            <form action="index.php?action=comenzar" method="post">
                <input type="hidden" name="_csrf" value="<?= csrf_token(); ?>">
                <button class="btn-primary-grid text-xl px-8 py-4 w-full sm:w-auto" type="submit">
                    START CHALLENGE <i class="fas fa-arrow-right ml-2"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- Right Column: Hero Image -->
    <div class="grid-col-span-6 grid-cell flex items-center justify-center bg-gray-50 relative overflow-hidden p-0">
        <div class="absolute inset-0 bg-[var(--db-orange)] opacity-5"
            style="background-image: radial-gradient(circle, #000 1px, transparent 1px); background-size: 20px 20px;">
        </div>

        <img src="assets/img/Shen_Long_Artwork.png" alt="Shenlong"
            class="floating-img relative z-10 w-full max-w-md object-contain p-8 filter drop-shadow-2xl">

        <div
            class="absolute bottom-0 right-0 bg-black text-white px-4 py-2 font-mono text-xs border-t-black border-l-black">
            SYSTEM: READY
        </div>
    </div>
</div>

<?php if (!empty($partidaId)): ?>
    <div class="border-t-black p-2 text-center font-mono text-xs text-gray-400">
        SESSION ID: <?= htmlspecialchars($partidaId) ?>
    </div>
<?php endif; ?>