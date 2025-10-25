document.addEventListener("DOMContentLoaded", () => {
  // --- 1. Fungsi Latar Belakang Biner ---
  function createBinaryFall() {
    const container = document.querySelector(".binary-background");
    if (!container) return; // Hentikan jika elemen tidak ditemukan

    const columnCount = Math.floor(window.innerWidth / 20); // Buat kolom berdasarkan lebar layar

    for (let i = 0; i < columnCount; i++) {
      const column = document.createElement("div");
      column.className = "binary-column";

      // Buat string biner acak
      let binaryString = "";
      for (let j = 0; j < 40; j++) {
        // Panjang string
        binaryString += Math.random() > 0.5 ? "1" : "0";
      }
      column.textContent = binaryString;

      // Acak properti untuk tampilan yang lebih natural
      column.style.left = `${Math.random() * 100}vw`;
      column.style.animationDuration = `${Math.random() * 8 + 7}s`; // Durasi 7-15 detik
      column.style.animationDelay = `${Math.random() * 10}s`; // Delay 0-10 detik
      column.style.fontSize = `${Math.random() * 8 + 10}px`; // Ukuran font 10-18px
      column.style.opacity = Math.random() * 0.1 + 0.05; // Opacity 0.05 - 0.15

      container.appendChild(column);
    }
  }

  // Panggil fungsi latar belakang
  createBinaryFall();

  // --- 2. Fungsi Efek Mengetik (Typewriter) yang Diperbaiki ---
  const textElement = document.getElementById("typing-text");
  const textToType = "Plant Asto ,Kita Satu Kita Bisa Kita Hebat"; // Simpan teks di JS
  let i = 0;
  let isDeleting = false;
  let isLoopRunning = false; // Status untuk mengontrol loop

  function typeWriterLoop() {
    // Cek kondisi layar
    if (window.innerWidth <= 768) {
      // Jika di mobile, tampilkan teks penuh & hentikan loop
      textElement.textContent = textToType;
      textElement.style.borderRight = "none";
      isLoopRunning = false;
      return;
    }

    // Jika di desktop, pastikan loop ditandai berjalan
    isLoopRunning = true;
    textElement.style.borderRight = "3px solid var(--accent-color)"; // Tampilkan kursor

    let currentText = textElement.textContent;
    let typeSpeed = 100;

    if (isDeleting) {
      // Proses Menghapus
      typeSpeed = 50;
      textElement.textContent = textToType.substring(0, currentText.length - 1);
    } else {
      // Proses Mengetik
      textElement.textContent = textToType.substring(0, currentText.length + 1);
    }

    // Logika untuk beralih mode (mengetik/menghapus)
    if (!isDeleting && textElement.textContent === textToType) {
      // Selesai mengetik -> tunggu -> mulai hapus
      isDeleting = true;
      typeSpeed = 2000; // Jeda 2 detik
    } else if (isDeleting && textElement.textContent === "") {
      // Selesai menghapus -> tunggu -> mulai ketik lagi
      isDeleting = false;
      typeSpeed = 500; // Jeda 0.5 detik
    }

    // Panggil fungsi selanjutnya dalam loop
    setTimeout(typeWriterLoop, typeSpeed);
  }

  // --- 3. Event Listener (Pemicu) ---

  // Pemicu saat ukuran jendela diubah
  window.addEventListener("resize", () => {
    if (window.innerWidth > 768 && !isLoopRunning) {
      // Jika berubah ke desktop DAN loop belum jalan
      i = 0;
      isDeleting = false;
      textElement.textContent = ""; // Kosongkan teks untuk mulai
      typeWriterLoop(); // Mulai loop
    } else if (window.innerWidth <= 768) {
      // Jika berubah ke mobile
      textElement.textContent = textToType; // Langsung tampilkan teks
      textElement.style.borderRight = "none"; // Sembunyikan kursor
      isLoopRunning = false; // Loop akan berhenti pada iterasi berikutnya
    }
  });

  // Pemicu awal saat halaman dimuat
  if (window.innerWidth > 768) {
    // Jika dimuat di desktop
    textElement.textContent = ""; // Kosongkan dulu
    typeWriterLoop(); // Mulai efek ketik
  }
  // Jika dimuat di mobile, tidak perlu melakukan apa-apa,
  // karena HTML sudah berisi teks yang benar.
});
