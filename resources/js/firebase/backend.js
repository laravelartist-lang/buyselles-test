import { initializeApp } from 'firebase/app';
import {
    getAuth,
    createUserWithEmailAndPassword,
    signInWithEmailAndPassword,
    signInWithPopup,
    GoogleAuthProvider,
    onAuthStateChanged,
    signOut,
} from 'firebase/auth';
import {
    getFirestore,
    collection,
    doc,
    getDoc,
    setDoc,
    addDoc,
    serverTimestamp,
} from 'firebase/firestore';

const firebaseConfig = window.__FIREBASE_CONFIG__ ?? {};

const app = initializeApp(firebaseConfig);
const auth = getAuth(app);
const db = getFirestore(app);
const googleProvider = new GoogleAuthProvider();

async function registerWithEmail(email, password) {
    const credential = await createUserWithEmailAndPassword(auth, email, password);
    await setDoc(doc(db, 'users', credential.user.uid), {
        email: credential.user.email,
        createdAt: serverTimestamp(),
    });

    return credential.user;
}

async function loginWithEmail(email, password) {
    const credential = await signInWithEmailAndPassword(auth, email, password);

    return credential.user;
}

async function loginWithGoogle() {
    const credential = await signInWithPopup(auth, googleProvider);
    const userRef = doc(db, 'users', credential.user.uid);
    const snapshot = await getDoc(userRef);

    if (! snapshot.exists()) {
        await setDoc(userRef, {
            email: credential.user.email,
            displayName: credential.user.displayName,
            photoURL: credential.user.photoURL,
            createdAt: serverTimestamp(),
        });
    }

    return credential.user;
}

async function logout() {
    await signOut(auth);
}

function onAuthChange(callback) {
    return onAuthStateChanged(auth, callback);
}

window.BuySellesFirebase = {
    app,
    auth,
    db,
    collection,
    doc,
    getDoc,
    setDoc,
    addDoc,
    serverTimestamp,
    registerWithEmail,
    loginWithEmail,
    loginWithGoogle,
    logout,
    onAuthChange,
};
