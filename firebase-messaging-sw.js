importScripts('https://www.gstatic.com/firebasejs/9.22.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.22.0/firebase-messaging-compat.js');

// 1. REPLACE WITH YOUR FIREBASE CONFIG
// Go to Firebase Console -> Project Settings -> General -> Your Apps -> SDK Setup
const firebaseConfig = {
  apiKey: "AIzaSyBRvdcvgMsOiVzeUQdSMYZFQ1GKkHZUWYI",
  authDomain: "otm-portal-312a5.firebaseapp.com",
  projectId: "otm-portal-312a5",
  storageBucket: "otm-portal-312a5.firebasestorage.app",
  messagingSenderId: "917547807534",
  appId: "1:917547807534:web:9021c744b7b0f62b4e80bf"
};

firebase.initializeApp(firebaseConfig);

const messaging = firebase.messaging();

// Handle background messages
messaging.onBackgroundMessage(function(payload) {
  console.log('[firebase-messaging-sw.js] Received background message ', payload);
  
  const notificationTitle = payload.notification.title;
  const notificationOptions = {
    body: payload.notification.body,
    icon: '/icon.png' // Upload a small icon.png to your server root if you want an icon
  };

  self.registration.showNotification(notificationTitle, notificationOptions);
});