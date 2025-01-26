<?php
/**
 * index.php
 * Esempio di chatbot AI con Gemini in un singolo file
 */

/* =========================================
   1) BACKEND: Gestione AJAX in PHP
   ========================================= */
if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
    // Imposta l'header per indicare che la risposta è JSON
    header('Content-Type: application/json');

    try {
        // Recupera il messaggio inviato dal form
        $userMessage = $_POST['message'] ?? '';

        if (!$userMessage) {
            throw new Exception('Nessun messaggio fornito');
        }

        // Recupera la chiave API dal file di configurazione
        $config = require_once 'config.php';
        $apiKey = $config['gemini_api_key'];

        if (!$apiKey) {
            throw new Exception('API key non configurata');
        }

        // Endpoint Gemini API
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $apiKey;

        // Configurazione del prompt di sistema per il supporto tecnico
        $systemPrompt = ""; // TODO: Aggiungi qui il prompt di sistema

        // Corpo della richiesta da inviare a Gemini
        $data = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $systemPrompt
                        ],
                        [
                            'text' => $userMessage
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'topP' => 0.8,
                'topK' => 40
            ]
        ];

        // Inizializza cURL
        $ch = curl_init($endpoint);
        if ($ch === false) {
            throw new Exception('Impossibile inizializzare cURL');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        // Esegui la richiesta
        $response = curl_exec($ch);
        
        if ($response === false) {
            throw new Exception('Errore cURL: ' . curl_error($ch));
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200) {
            throw new Exception("Errore HTTP $status: $response");
        }

        // Decodifica JSON di risposta
        $responseData = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Errore nella decodifica JSON: ' . json_last_error_msg());
        }

        if (isset($responseData['error'])) {
            throw new Exception('Errore API: ' . ($responseData['error']['message'] ?? 'Errore sconosciuto'));
        }

        if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception('Formato risposta non valido');
        }

        echo json_encode(['reply' => $responseData['candidates'][0]['content']['parts'][0]['text']]);

    } catch (Exception $e) {
        error_log('Errore chatbot: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

/* =========================================
   2) FRONTEND: HTML/JS/Tailwind
   =========================================
   Se NON è una richiesta AJAX, mostriamo la pagina.
 */
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Supporto Tecnico IT</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
  <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
  <!-- Marked.js per il parsing Markdown -->
  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
  <!-- Highlight.js per la sintassi del codice -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/github.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>
  
  <style>
    /* Mobile-first base styles */
    :root {
      --safe-area-inset-bottom: env(safe-area-inset-bottom, 0px);
    }

    body {
      -webkit-tap-highlight-color: transparent;
    }

    /* Stili per il markdown renderizzato */
    .markdown-content {
      line-height: 1.6;
      font-size: 0.95rem;
    }
    .markdown-content p {
      margin-bottom: 0.5rem;
    }
    .markdown-content code {
      background: #f0f0f0;
      padding: 0.2em 0.4em;
      border-radius: 3px;
      font-size: 0.9em;
    }
    .markdown-content pre code {
      background: transparent;
      padding: 0;
    }
    .markdown-content pre {
      background: #f6f8fa;
      padding: 1em;
      border-radius: 6px;
      overflow-x: auto;
      margin: 0.5rem 0;
    }
    .markdown-content ul, .markdown-content ol {
      padding-left: 1.5rem;
      margin: 0.5rem 0;
    }
    .markdown-content ul {
      list-style-type: disc;
    }
    .markdown-content ol {
      list-style-type: decimal;
    }
    .markdown-content a {
      color: #6366f1;
      text-decoration: underline;
    }
    .typing-indicator {
      display: flex;
      align-items: center;
      padding: 0.75rem 1rem;
      background: rgba(255, 255, 255, 0.9);
      border-radius: 1rem;
      box-shadow: 0 1px 2px rgba(0,0,0,0.1);
      margin-bottom: 0.5rem;
    }
    .typing-indicator-avatar {
      width: 2rem;
      height: 2rem;
      border-radius: 50%;
      background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 0.75rem;
    }
    .typing-indicator-dots {
      display: flex;
      gap: 0.25rem;
    }
    .typing-indicator-dot {
      width: 0.5rem;
      height: 0.5rem;
      background: #6b7280;
      border-radius: 50%;
      animation: typing 1.4s infinite;
      opacity: 0.4;
    }
    .typing-indicator-dot:nth-child(2) { animation-delay: 0.2s; }
    .typing-indicator-dot:nth-child(3) { animation-delay: 0.4s; }
    @keyframes typing {
      0%, 100% { opacity: 0.4; }
      50% { opacity: 1; }
    }

    /* Mobile-optimized chat container */
    @media (max-width: 768px) {
      #chatContainer {
        position: fixed;
        inset: 0;
        margin: 0;
        border-radius: 0;
        padding-bottom: calc(var(--safe-area-inset-bottom));
      }

      #chatForm {
        position: sticky;
        bottom: 0;
        background: white;
        padding: 0.75rem;
        padding-bottom: calc(0.75rem + var(--safe-area-inset-bottom));
        box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
      }

      #chatbox {
        padding-bottom: 1rem;
      }
    }
  </style>
</head>
<body class="bg-gradient-to-br from-indigo-600 to-purple-700 min-h-screen flex flex-col">
  <!-- HEADER -->
  <header class="p-4 sm:p-8 text-white text-center" data-aos="fade-down">
    <h1 class="text-3xl sm:text-4xl font-extrabold drop-shadow-lg bg-clip-text text-transparent bg-gradient-to-r from-white to-purple-200">
      Supporto Tecnico IT
    </h1>
    <p class="mt-2 sm:mt-3 text-white opacity-90 text-lg sm:text-xl font-light px-4">
      Assistenza professionale per i tuoi problemi tecnici
    </p>
  </header>

  <!-- CONTENUTO PRINCIPALE -->
  <main class="flex-1 flex flex-col items-center justify-center text-white p-4 gap-6 sm:gap-8">
    <div class="w-full max-w-2xl grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6 px-0 sm:px-4">
      <div class="bg-white bg-opacity-10 backdrop-blur-lg p-4 sm:p-6 rounded-xl shadow-xl" data-aos="fade-right">
        <div class="flex items-center mb-3 sm:mb-4">
          <svg class="w-6 h-6 sm:w-8 sm:h-8 text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
          </svg>
          <h2 class="text-lg sm:text-xl font-bold ml-3">Supporto Esperto</h2>
        </div>
        <p class="leading-relaxed text-sm sm:text-base text-gray-100">
          Assistenza tecnica professionale per problemi hardware, software e di rete con soluzioni precise e verificate.
        </p>
      </div>

      <div class="bg-white bg-opacity-10 backdrop-blur-lg p-4 sm:p-6 rounded-xl shadow-xl" data-aos="fade-left">
        <div class="flex items-center mb-3 sm:mb-4">
          <svg class="w-6 h-6 sm:w-8 sm:h-8 text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/>
          </svg>
          <h2 class="text-lg sm:text-xl font-bold ml-3">Analisi Dettagliata</h2>
        </div>
        <p class="leading-relaxed text-sm sm:text-base text-gray-100">
          Raccolta sistematica delle informazioni necessarie per identificare e risolvere il tuo problema tecnico.
        </p>
      </div>
    </div>

    <div class="text-center mt-4 sm:mt-8 px-4" data-aos="fade-up">
      <p class="text-base sm:text-lg mb-3 sm:mb-4 text-purple-200">Hai bisogno di assistenza tecnica?</p>
      <button
        id="chatToggleBtn"
        class="w-full sm:w-auto bg-white text-purple-700 px-6 sm:px-8 py-3 rounded-full font-semibold
               hover:bg-purple-100 transform hover:scale-105 transition-all duration-300
               shadow-lg hover:shadow-xl text-base sm:text-lg"
      >
        Contatta il supporto
      </button>
    </div>
  </main>

  <!-- CONTENITORE DELLA CHAT -->
  <div
    id="chatContainer"
    class="hidden fixed inset-0 bg-white flex flex-col
           md:max-w-md md:mx-auto md:my-10 md:rounded-2xl md:shadow-2xl
           z-50"
  >
    <!-- HEADER CHAT -->
    <div class="bg-gradient-to-r from-indigo-600 to-purple-700 text-white py-3 sm:py-4 px-4 sm:px-6 
                flex justify-between items-center md:rounded-t-2xl">
      <h3 class="text-base sm:text-lg font-bold">Supporto Tecnico IT</h3>
      <button
        id="closeChatBtn"
        class="text-white focus:outline-none hover:bg-white/20 rounded-full p-1.5 sm:p-2 transition"
      >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>

    <!-- CORPO DELLA CHAT -->
    <div id="chatbox" class="flex-1 overflow-y-auto p-3 sm:p-4 space-y-2 sm:space-y-3 bg-gray-50">
      <!-- Messaggi verranno aggiunti qui -->
    </div>

    <!-- FORM INVIO MESSAGGI -->
    <form id="chatForm" class="flex border-t border-gray-200 p-2 sm:p-3 gap-2">
      <input
        type="text"
        id="userMessage"
        class="flex-grow px-3 sm:px-4 py-2 text-sm sm:text-base rounded-full border border-gray-300 
               focus:outline-none focus:border-purple-500"
        placeholder="Descrivi il tuo problema tecnico..."
        required
      />
      <button
        type="submit"
        class="bg-gradient-to-r from-indigo-600 to-purple-700 text-white px-4 sm:px-6 py-2 
               rounded-full hover:opacity-90 transition-opacity whitespace-nowrap text-sm sm:text-base"
      >
        Invia
      </button>
    </form>
  </div>

  <script>
    // Inizializza AOS
    AOS.init({
      duration: 1000,
      once: true
    });
    
    // Selettori e gestione UI
    const chatToggleBtn    = document.getElementById('chatToggleBtn');
    const chatContainer    = document.getElementById('chatContainer');
    const closeChatBtn     = document.getElementById('closeChatBtn');
    const chatbox          = document.getElementById('chatbox');
    const chatForm         = document.getElementById('chatForm');
    const userMessageInput = document.getElementById('userMessage');

    // Mostra la chat al click
    chatToggleBtn.addEventListener('click', () => {
      chatContainer.classList.remove('hidden');
      chatbox.scrollTop = chatbox.scrollHeight;
    });

    // Chiudi la chat
    closeChatBtn.addEventListener('click', () => {
      chatContainer.classList.add('hidden');
    });

    // Aggiunge messaggi nella chat
    function addMessage(text, sender) {
      const wrapper = document.createElement('div');
      const messageBubble = document.createElement('div');
      const baseClasses = 'max-w-[80%] px-4 py-3 rounded-2xl shadow-sm';

      if (sender === 'user') {
        wrapper.className = 'flex justify-end mb-3';
        messageBubble.className = baseClasses + ' bg-gradient-to-r from-indigo-600 to-purple-700 text-white ml-auto';
        messageBubble.textContent = text;
      } else if (sender === 'bot') {
        wrapper.className = 'flex justify-start mb-3';
        messageBubble.className = baseClasses + ' bg-gray-100 text-gray-800 markdown-content';
        // Parsing Markdown per i messaggi del bot
        messageBubble.innerHTML = marked.parse(text);
        // Evidenzia la sintassi del codice
        messageBubble.querySelectorAll('pre code').forEach((block) => {
          hljs.highlightElement(block);
        });
      } else {
        // error
        wrapper.className = 'flex justify-start mb-3';
        messageBubble.className = baseClasses + ' bg-red-100 text-red-800';
        messageBubble.textContent = text;
      }

      // Aggiungi effetto di fade-in
      wrapper.style.opacity = '0';
      wrapper.style.transform = 'translateY(20px)';
      wrapper.style.transition = 'all 0.3s ease-out';

      wrapper.appendChild(messageBubble);
      chatbox.appendChild(wrapper);

      // Trigger animation
      setTimeout(() => {
        wrapper.style.opacity = '1';
        wrapper.style.transform = 'translateY(0)';
      }, 50);

      // Aggiungi suono di notifica per i messaggi del bot
      if (sender === 'bot') {
        const audio = new Audio('data:audio/mp3;base64,SUQzBAAAAAABEVRYWFgAAAAtAAADY29tbWVudABCaWdTb3VuZEJhbmsuY29tIC8gTGFTb25vdGhlcXVlLm9yZwBURU5DAAAAHQAAA1N3aXRjaCBQbHVzIMKpIE5DSCBTb2Z0d2FyZQBUSVQyAAAABgAAAzIyMzUAVFNTRQAAAA8AAANMYXZmNTcuODMuMTAwAAAAAAAAAAAAAAD/80DEAAAAA0gAAAAATEFNRTMuMTAwVVVVVVVVVVVVVUxBTUUzLjEwMFVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVf/zQsRbAAADSAAAAABVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVf/zQMSkAAADSAAAAABVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVV');
        audio.volume = 0.2;
        audio.play().catch(() => {}); // Ignora errori se l'autoplay è bloccato
      }

      return wrapper; // Ritorna il wrapper per poterlo rimuovere se necessario
    }

    // Funzione per mostrare l'indicatore "sta scrivendo"
    function showTypingIndicator() {
      const wrapper = document.createElement('div');
      wrapper.className = 'flex justify-start';
      wrapper.innerHTML = `
        <div class="typing-indicator">
          <div class="typing-indicator-avatar">
            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                    d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
            </svg>
          </div>
          <div class="text-sm text-gray-500 mr-3">L'operatore sta scrivendo</div>
          <div class="typing-indicator-dots">
            <div class="typing-indicator-dot"></div>
            <div class="typing-indicator-dot"></div>
            <div class="typing-indicator-dot"></div>
          </div>
        </div>
      `;
      chatbox.appendChild(wrapper);
      return wrapper;
    }

    // Modifica la gestione dell'invio messaggi
    chatForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const message = userMessageInput.value.trim();
      if (!message) return;

      try {
        // Disabilita il form durante l'invio
        const submitButton = chatForm.querySelector('button[type="submit"]');
        submitButton.disabled = true;
        userMessageInput.disabled = true;

        // Mostra il messaggio dell'utente
        addMessage(message, 'user');
        userMessageInput.value = '';

        // Mostra l'indicatore "sta scrivendo"
        const typingIndicator = showTypingIndicator();

        // Costruisci l'URL completo
        const currentUrl = window.location.href.split('?')[0];
        
        // Chiamata AJAX
        const response = await fetch(currentUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: new URLSearchParams({
            ajax: '1',
            message: message
          })
        });

        // Rimuovi l'indicatore "sta scrivendo"
        typingIndicator.remove();

        if (!response.ok) {
          throw new Error(`Errore HTTP: ${response.status}`);
        }

        const data = await response.json();

        if (data.error) {
          throw new Error(data.error);
        }

        // Simula un breve ritardo prima di mostrare la risposta (più naturale)
        setTimeout(() => {
          addMessage(data.reply, 'bot');
        }, 500);

      } catch (error) {
        console.error('Errore:', error);
        addMessage('Mi dispiace, si è verificato un errore di comunicazione. Per favore, riprova tra qualche istante.', 'error');
      } finally {
        // Riabilita il form
        const submitButton = chatForm.querySelector('button[type="submit"]');
        submitButton.disabled = false;
        userMessageInput.disabled = false;
        
        // Scrolla in basso
        chatbox.scrollTop = chatbox.scrollHeight;
      }
    });

    // Aggiungi messaggio di benvenuto quando la chat viene aperta
    chatToggleBtn.addEventListener('click', () => {
      if (chatbox.children.length === 0) {
        setTimeout(() => {
          addMessage('Messaggio di benvenuto', 'bot');
        }, 500);
      }
    });
  </script>
</body>
</html>
