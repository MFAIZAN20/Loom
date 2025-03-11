</main>
    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> loom </p>
        </div>
    </footer>
    <script src="js/main.js"></script>
    <?php if (isset($page_js)): ?>
        <script src="js/<?php echo $page_js; ?>"></script>
    <?php endif; ?>
</body>
</html>