<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title> Sidebar - Sistema de Gestão</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
  <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css">
</head>
<body>
    <!-- btn - dark-mode -->
    <div class="dark-mode-toggle" id="darkModeToggle">
        <i class="fas fa-sun toggle-icon sun"></i>
        <i class="fas fa-moon toggle-icon moon"></i>
    </div>  

    <style>
        /* Toggle switch for dark mode */
        .dark-mode-toggle {
          display: flex;
          justify-content: center;
          align-items: center;
          position: fixed;
          top: 0.6rem;
          right: 0.6rem;
          z-index: 1001;
          width: 40px;
          height: 40px;
          border-radius: 50%;
          background-color: #f8f9fa;
          color: #212529;
          box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        
        .sun, .moon {
          display: none;
          font-size: 1rem;
          line-height: 40px;
          text-align: center;
          width: 100%;
        }

        body.dark-mode .sun {
          display: block;
        }

        body:not(.dark-mode) .moon {
          display: block;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
        const toggleBtn = document.getElementById('darkModeToggle');
        const darkModeAtivo = localStorage.getItem('darkMode') === 'true';

        if (darkModeAtivo) {
          document.body.classList.add('dark-mode');
        }
    
        toggleBtn.addEventListener('click', function () {
          document.body.classList.toggle('dark-mode');
          const isDark = document.body.classList.contains('dark-mode');
          localStorage.setItem('darkMode', isDark);
        });
    });
    </script>
</body>
</html>