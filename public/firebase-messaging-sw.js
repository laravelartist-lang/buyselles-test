importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-messaging.js');
importScripts('https://www.gstatic.com/firebasejs/8.3.2/firebase-auth.js');

firebase.initializeApp({
    apiKey: "AIzaSyCiir3f-99LfW81gecqocO1I7I1xwMOo4U",
    authDomain: "buyselles-production.firebaseapp.com",
    projectId: "buyselles-production",
    storageBucket: "buyselles-production.firebasestorage.app",
    messagingSenderId: "981094374492",
    appId: "1:981094374492:web:62bd0fb64f44dcebce5a99",
    measurementId: "G-WVNMH70N6K"
});

const messaging = firebase.messaging();
messaging.setBackgroundMessageHandler(function(payload) {
    return self.registration.showNotification(payload.data.title, {
        body: payload.data.body || '',
        icon: payload.data.icon || ''
    });
});
