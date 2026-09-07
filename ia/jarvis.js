document.addEventListener("DOMContentLoaded", () => {
    
    const widget = document.getElementById("jarvis-widget");

    widget.innerHTML = `
        <div id="jarvis-launcher">💬</div>

        <div id="jarvis-window" class="oculto">
            <div id="jarvis-header">
                <span>JARVIS - Asistente IA</span>
                <button id="jarvis-close">&times;</button>
            </div>

            <div id="jarvis-messages"></div>

            <div id="jarvis-input">
                <input type="text" id="jarvis-msg" placeholder="Escribe algo...">
                <button id="jarvis-send">Enviar</button>
            </div>
        </div>
    `;

    const launcher = document.getElementById("jarvis-launcher");
    const windowChat = document.getElementById("jarvis-window");
    const closeBtn = document.getElementById("jarvis-close");
    const sendBtn = document.getElementById("jarvis-send");
    const messageBox = document.getElementById("jarvis-messages");
    const input = document.getElementById("jarvis-msg");

    launcher.onclick = () => windowChat.classList.toggle("oculto");
    closeBtn.onclick = () => windowChat.classList.add("oculto");

    function addUser(msg) {
        messageBox.innerHTML += `
            <div class="message user">
                <div class="bubble">${msg}</div>
            </div>
        `;
        messageBox.scrollTop = messageBox.scrollHeight;
    }

    function addBot(msg) {
        messageBox.innerHTML += `
            <div class="message bot">
                <div class="bubble">${msg}</div>
            </div>
        `;
        messageBox.scrollTop = messageBox.scrollHeight;
    }

    function sendMsg() {
        const msg = input.value.trim();
        if (!msg) return;

        addUser(msg);
        input.value = "";

        fetch("./chatbot.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: "mensaje=" + encodeURIComponent(msg)
        })
        .then(res => res.json())
        .then(data => {
            addBot(data.respuesta || "Error en la respuesta.");
        })
        .catch(() => addBot("Error al conectar con JARVIS."));
    }

    sendBtn.onclick = sendMsg;
    input.onkeypress = (e) => { if (e.key === "Enter") sendMsg(); };
});
