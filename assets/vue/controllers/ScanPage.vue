<template>
    <div class="text-center">
        <video ref="video" @loadedmetadata="initVideo" autoplay muted playsinline></video>
    </div>
</template>

<script>
    import QrCode from 'qrcode-reader';
    import axios from 'axios';
    import Swal from 'sweetalert2';

    export default {
        mounted() {
            this.initCamera();
        },
        props: {
            checkUrl: {
                type: String,
                required: true
            }
        },
        methods: {
            async initCamera() {
                try {
                    // Vérification de support des APIs modernes
                    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur',
                            text: 'Votre appareil ou navigateur ne prend pas en charge la capture vidéo.'
                        });
                        return;
                    }

                    // Vérifier si les permissions sont accordées
                    const permissions = await navigator.permissions.query({ name: 'camera' });
                    if (permissions.state !== 'granted') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Autorisation requise',
                            text: 'Veuillez autoriser l’accès à la caméra pour continuer.'
                        });
                    }

                    // Récupérer l'ID de la caméra arrière
                    const rearCameraId = await this.getRearCamera();
                    if (!rearCameraId) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erreur caméra',
                            text: 'Impossible de trouver une caméra arrière.'
                        });
                        return;
                    }

                    // Définir les contraintes pour la caméra arrière
                    const constraints = {
                        video: {
                            deviceId: rearCameraId ? { exact: rearCameraId } : undefined,
                            facingMode: { ideal: "environment" }
                        }
                    };

                    // Activer le flux vidéo
                    const stream = await navigator.mediaDevices.getUserMedia(constraints);
                    if (stream && stream instanceof MediaStream) {
                        const video = this.$refs.video;
                        video.srcObject = stream;
                        video.play();
                    }
                } catch (err) {
                    console.error('Erreur lors de l’initialisation de la caméra:', err.message);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur caméra',
                        text: 'Impossible d’activer la caméra. Vérifiez vos permissions ou redémarrez votre appareil.'
                    });
                }
            },

            async getRearCamera() {
                try {
                    const devices = await navigator.mediaDevices.enumerateDevices();
                    const videoDevices = devices.filter(device => device.kind === 'videoinput');

                    // Tenter de trouver une caméra "arrière"
                    let rearCamera = videoDevices.find(device =>
                        device.label.toLowerCase().includes('back') ||
                        device.label.toLowerCase().includes('rear')
                    );

                    // Si non trouvée, sélectionner le second appareil ou le premier
                    if (!rearCamera && videoDevices.length > 1) {
                        rearCamera = videoDevices[1];
                    }
                    return rearCamera ? rearCamera.deviceId : null;
                } catch (err) {
                    console.error('Erreur lors de la récupération des caméras :', err.message);
                    return null;
                }
            },

            initVideo() {
                const video = this.$refs.video;
                const qr = new QrCode();

                // Callback pour le décodage des QR codes
                qr.callback = (err, value) => {
                    if (err) {
                        console.warn('Erreur de décodage QR:', err.message);
                    } else {
                        this.handleQRCode(value.result);
                    }
                };

                // Fonction de capture de frame pour le décodage
                let isDecoding = false;
                const captureFrame = () => {
                    if (isDecoding) {
                        requestAnimationFrame(captureFrame);
                        return;
                    }

                    isDecoding = true;

                    // Création d'un canvas temporaire pour capturer la vidéo
                    const canvas = document.createElement('canvas');
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;

                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);

                    try {
                        qr.decode(imageData);
                    } catch (decodeError) {
                        console.warn('Décodage échoué:', decodeError.message);
                    }

                    // Pause entre les décodages
                    setTimeout(() => {
                        isDecoding = false;
                    }, 500);

                    requestAnimationFrame(captureFrame);
                };
                requestAnimationFrame(captureFrame);
            },

            async handleQRCode(qrCodeData) {
                try {
                    // Validation du QR code via API
                    const response = await axios.post(this.checkUrl, { 'url': qrCodeData });

                    if (response.status === 200) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Succès',
                            text: response.data.message
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Code invalide',
                            text: 'Le QR code scanné est invalide.'
                        });
                    }
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: 'Impossible de valider le QR code. Réessayez.'
                    });
                }
            }
        }
    };
</script>