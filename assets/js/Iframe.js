window.onload = () => {
    LazyLoad();
}
window.addEventListener("scroll", function() {
    LazyLoad();
})
function LazyLoad(){
    const imageObserver = new IntersectionObserver((entries, imgObserver) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                const lazyImage = entry.target
                if(lazyImage.dataset.srcset){
                    lazyImage.srcset = lazyImage.dataset.srcset
                }
                if(lazyImage.dataset.src){
                    lazyImage.src = lazyImage.dataset.src
                }
                imgObserver.unobserve(lazyImage);
            }
        })
    });
    const arr = document.querySelectorAll('iframe,img');
    arr.forEach((v) => {
        if(v.classList.contains('lazy')){
            v.classList.remove('lazy');
        }
        imageObserver.observe(v);
    }) 
}