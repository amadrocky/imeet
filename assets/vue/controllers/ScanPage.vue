<template>
    <div class="text-center">
        <video ref="video" @loadedmetadata="initVideo"></video>
    </div>
</template>
  
<script>
    import QrCode from 'qrcode-reader';
    import axios from 'axios';
    import Swal from 'sweetalert2'

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
            initCamera() {
            const video = this.$refs.video;

            navigator.mediaDevices.getUserMedia({ video: true }).then(stream => {
                video.srcObject = stream;
                video.play();
            }).catch(err => console.error('Failed to initialize camera:', err));
            },
            initVideo() {
                const video = this.$refs.video;
                video.play();

                const qr = new QrCode();
                qr.callback = (err, value) => {
                    if (err) {
                        // nothing
                    } else {
                        this.handleQRCode(value.result);
                    }
                };
                
                const captureFrame = () => {
                    const canvas = document.createElement('canvas');
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;

                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    qr.decode(imageData);
                    requestAnimationFrame(captureFrame);
                };
                requestAnimationFrame(captureFrame);
            },
            async handleQRCode(qrCodeData) {
                try {
                    const response = await axios.post(this.checkUrl, { 'url': qrCodeData });

                    if (response.status === 200) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Succès',
                            text: response.data.message
                        });
                    }
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Code invalide',
                        text: false
                    });
                }
            }
        }
    };
</script>
  
