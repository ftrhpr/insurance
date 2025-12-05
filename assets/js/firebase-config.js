/**
 * Firebase Configuration Module
 * Handles push notification setup
 */

const firebaseConfig = {
    apiKey: "AIzaSyBRvdcvgMsOiVzeUQdSMYZFQ1GKkHZUWYI",
    authDomain: "otm-portal-312a5.firebaseapp.com",
    projectId: "otm-portal-312a5",
    storageBucket: "otm-portal-312a5.firebasestorage.app",
    messagingSenderId: "917547807534",
    appId: "1:917547807534:web:9021c744b7b0f62b4e80bf"
};

// Initialize Firebase
try {
    firebase.initializeApp(firebaseConfig);
    const messaging = firebase.messaging();
    
    // Handle foreground messages
    messaging.onMessage((payload) => {
        console.log('Message received. ', payload);
        const { title, body } = payload.notification;
        showToast(title, body, 'urgent');
        
        // Show browser notification
        if (Notification.permission === 'granted') {
            new Notification(title, { body, icon: '/favicon.ico' });
        }
    });

    window.requestNotificationPermission = async function() {
        try {
            const permission = await Notification.requestPermission();
            if (permission === 'granted') {
                const token = await messaging.getToken({
                    vapidKey: 'BNxZ9Y4qX8kQ7wL3mR2vD5pC1nH6jG8fK4sT7yU9oP2aE3bV4cW5xZ6yA1qR3tN8mL9kJ0hF2gD4sA5pO7iU8yT'
                });
                
                if (token) {
                    await fetchAPI('register_token', 'POST', { token });
                    showToast('Notifications Enabled', 'You will receive updates', 'success');
                    document.getElementById('btn-notify').classList.add('hidden');
                }
            }
        } catch (error) {
            console.error('Error getting notification permission:', error);
        }
    };
    
    window.enableNotifications = async function() {
        await window.requestNotificationPermission();
        document.getElementById('notification-prompt').remove();
    };

} catch (error) {
    console.error('Firebase initialization error:', error);
}
