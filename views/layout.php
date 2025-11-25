<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Akinator de Dragon Ball - ¡Piensa en un personaje y lo adivinaré!">
  <title>Akineitor - Dragon Ball Edition</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Kanit:ital,wght@0,600;0,800;1,800&display=swap"
    rel="stylesheet">

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

  <script src="https://cdn.tailwindcss.com"></script>

  <link rel="stylesheet" href="assets/style_modern.css">
</head>

<body class="bg-grid-pattern">
  <div class="main-container">
    <header class="header-bar">
      <div class="font-title font-bold text-xl tracking-tighter">AKINEITOR <span
          class="text-[var(--db-orange)]">DBZ</span></div>
      <div class="text-xs font-mono uppercase tracking-widest">Sistema Activo</div>
    </header>

    <main class="flex-grow flex flex-col justify-center">
      <?php require $VIEW_FILE; ?>
    </main>

    <footer class="border-t-black p-4 text-center text-xs font-mono uppercase text-gray-400">
      Tecnología Capsule Corp. &copy; <?= date('Y') ?>
    </footer>
  </div>
</body>

</html>