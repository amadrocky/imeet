<template>
    <div>
        <video ref="video"></video>
    </div>
</template>
  
<script>
import QrCode from 'qrcode-reader';

export default {
    mounted() {
        const video = this.$refs.video;
        navigator.mediaDevices.getUserMedia({ video: true }).then(stream => {
            video.srcObject = stream;
            video.play();
            const qr = new QrCode();
            qr.callback = (err, value) => {
                if (err) {
                    console.log(err);
                } else {
                    console.log(value.result);
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
        }).catch(err => console.log(err));
    }
};
</script>
  
