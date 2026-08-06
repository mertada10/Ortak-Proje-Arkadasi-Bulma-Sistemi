const profileBtn = document.getElementById("profileBtn");
const profileMenu = document.getElementById("profileMenu");
const mobileMenuToggle = document.getElementById("mobileMenuToggle");
const navLinks = document.getElementById("navLinks");

if (mobileMenuToggle && navLinks) {
    mobileMenuToggle.addEventListener("click", function () {
        navLinks.classList.toggle("show");
        const icon = mobileMenuToggle.querySelector("i");
        if (navLinks.classList.contains("show")) {
            icon.classList.replace("fa-bars", "fa-xmark");
        } else {
            icon.classList.replace("fa-xmark", "fa-bars");
        }
    });
}

if (profileBtn && profileMenu) {


    profileBtn.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();
        profileMenu.classList.toggle("show");
    });

    document.addEventListener("click", function (e) {
        if (!profileBtn.contains(e.target) && !profileMenu.contains(e.target)) {
            profileMenu.classList.remove("show");
        }
    });
}


// =================================
// Sohbet Sistemi (AJAX)
// =================================

document.addEventListener("DOMContentLoaded", function () {

    const chatMessages = document.getElementById("chatMessages");
    const chatForm = document.getElementById("chatForm");
    const textarea = document.getElementById("messageInput");
    const receiverId = document.getElementById("receiverId");

    if (chatMessages && chatForm && textarea && receiverId) {

        // Mesajları yükle
        function loadMessages(scrollBottom = false) {

            fetch("message-api.php?type=private&id=" + receiverId.value)
                .then(response => response.text())
                .then(data => {

                    const oldHeight = chatMessages.scrollHeight;

                    chatMessages.innerHTML = data;

                    if (scrollBottom || chatMessages.scrollTop + chatMessages.clientHeight >= oldHeight - 100) {
                        chatMessages.scrollTop = chatMessages.scrollHeight;
                    }

                });

        }

        // İlk yükleme
        loadMessages(true);

        textarea.focus();

        // Enter ile gönder
        textarea.addEventListener("keydown", function (e) {

            if (e.key === "Enter" && !e.shiftKey) {

                e.preventDefault();

                chatForm.requestSubmit();

            }

        });

        // Mesaj gönder
        chatForm.addEventListener("submit", function (e) {

            e.preventDefault();

            const message = textarea.value.trim();

            if (message === "") return;

            const formData = new FormData();

            formData.append("receiver_id", receiverId.value);
            formData.append("message", message);

            fetch("message-api.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.text())
            .then(() => {

                textarea.value = "";
                textarea.focus();

                loadMessages(true);

            });

        });

        // 2 saniyede bir kontrol et
        setInterval(function () {
            loadMessages(false);
        }, 2000);
    }

    const teamChatWidget = document.getElementById("teamChatWidget");
    const teamChatToggle = document.getElementById("teamChatToggle");
    const teamChatClose = document.getElementById("teamChatClose");
    const teamChatMessages = document.getElementById("teamChatMessages");
    const teamChatForm = document.getElementById("teamChatForm");
    const teamTextarea = document.getElementById("teamMessageInput");
    const teamId = document.getElementById("teamId");

    if (teamChatToggle && teamChatWidget) {
        teamChatToggle.addEventListener("click", function () {
            teamChatWidget.classList.toggle("open");
        });
    }

    if (teamChatClose && teamChatWidget) {
        teamChatClose.addEventListener("click", function () {
            teamChatWidget.classList.remove("open");
        });
    }

function pingOnline(){

    fetch("/teammate-finder/ping-online.php");

}

pingOnline();

setInterval(
    pingOnline,
    30000
);

function updateUserStatus(){

    const badge = document.getElementById("chatStatusBadge");

    if(!badge){
        return;
    }

    let id = badge.dataset.receiverId;

    fetch("/teammate-finder/get-user-status.php?id="+id)

    .then(res=>res.json())

    .then(data=>{

        if(data.status=="online"){

            badge.innerHTML="Çevrimiçi";

            badge.classList.add("online");
            badge.classList.remove("offline");

        }
        else{

            badge.innerHTML="Çevrimdışı";

            badge.classList.add("offline");
            badge.classList.remove("online");

        }
    });
}

updateUserStatus();

setInterval(
    updateUserStatus,
    10000
);

    if (teamChatMessages && teamChatForm && teamTextarea && teamId) {

    function loadTeamMessages(scrollBottom = false) {

        fetch("message-api.php?type=team&id=" + teamId.value)
            .then(response => response.text())
            .then(data => {

                const oldHeight = teamChatMessages.scrollHeight;

                teamChatMessages.innerHTML = data;

                if (
                    scrollBottom ||
                    teamChatMessages.scrollTop + teamChatMessages.clientHeight >= oldHeight - 100
                ) {
                    teamChatMessages.scrollTop = teamChatMessages.scrollHeight;
                }

            });

    }

    loadTeamMessages(true);

    teamTextarea.addEventListener("keydown", function (e) {

        if (e.key === "Enter" && !e.shiftKey) {

            e.preventDefault();

            teamChatForm.requestSubmit();

        }

    });

    teamChatForm.addEventListener("submit", function (e) {

        e.preventDefault();

        const message = teamTextarea.value.trim();

        if(message === "") return;

        const formData = new FormData();

        formData.append("type","team");
        formData.append("team_id",teamId.value);
        formData.append("message",message);

        fetch("message-api.php", {

            method:"POST",
            body:formData

        })

        .then(response=>response.text())

        .then(data=>{

            console.log(data);

            if(data.trim()=="success"){

                teamTextarea.value="";

                loadTeamMessages(true);

            }
        });
    });

    setInterval(function(){

        loadTeamMessages(false);

    },2000);
}

});

function updateMultiSelectText(menu, textElement, fallbackText) {
    const selected = [];

    menu.querySelectorAll("input:checked").forEach(function (item) {
        selected.push(item.value);
    });

    textElement.textContent = selected.length
        ? selected.join(", ")
        : fallbackText;
}

function initMultiSelect(menuId, buttonId, textId, fallbackText) {
    const menu = document.getElementById(menuId);
    const button = document.getElementById(buttonId);
    const textElement = document.getElementById(textId);

    if (!menu || !button || !textElement) {
        return;
    }

    button.addEventListener("click", function (e) {
        e.stopPropagation();
        menu.classList.toggle("show");
    });

    document.addEventListener("click", function () {
        menu.classList.remove("show");
    });

    menu.addEventListener("click", function (e) {
        e.stopPropagation();
        window.setTimeout(function () {
            updateMultiSelectText(menu, textElement, fallbackText);
        }, 0);
    });

    menu.addEventListener("change", function () {
        window.setTimeout(function () {
            updateMultiSelectText(menu, textElement, fallbackText);
        }, 0);
    });

    updateMultiSelectText(menu, textElement, fallbackText);
}

document.addEventListener("DOMContentLoaded", function () {
    initMultiSelect("skillsMenu", "skillsBtn", "skillsText", "Teknoloji Seçiniz");
    initMultiSelect("interestsMenu", "interestsBtn", "interestsText", "İlgi Alanı Seçiniz");
});

const searchInput = document.getElementById("searchInput");

if (searchInput) {

    let timeout;

    searchInput.addEventListener("input", function () {

        clearTimeout(timeout);

        timeout = setTimeout(function () {

            fetch("/teammate-finder/pages/search-projects.php?search=" + encodeURIComponent(searchInput.value))
                .then(response => response.text())
                .then(data => {

                    document.getElementById("projectList").innerHTML = data;

                });

        }, 300);

    });

}

const menuToggle = document.getElementById("menuToggle");
const navbarMenu = document.getElementById("navbarMenu");

if(menuToggle && navbarMenu){

    menuToggle.addEventListener("click",function(){

        navbarMenu.classList.toggle("show");

    });

}

document.querySelectorAll(".toggle-password").forEach(function(icon){

    icon.addEventListener("click",function(){

        const input=this.previousElementSibling;

        if(input.type==="password"){

            input.type="text";
            this.classList.remove("fa-eye");
            this.classList.add("fa-eye-slash");

        }else{

            input.type="password";
            this.classList.remove("fa-eye-slash");
            this.classList.add("fa-eye");

        }

    });

});