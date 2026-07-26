let facebookSdkPromise = null

const initializeFacebook = (appId, graphVersion) => {
  window.FB.init({
    appId,
    cookie: true,
    xfbml: false,
    version: graphVersion,
  })

  return window.FB
}

export const loadFacebookSdk = (appId, graphVersion) => {
  if (!appId || !graphVersion) {
    return Promise.reject(new Error('Chưa cấu hình Facebook App ID hoặc Graph API version.'))
  }

  if (window.FB) {
    return Promise.resolve(initializeFacebook(appId, graphVersion))
  }

  if (facebookSdkPromise) return facebookSdkPromise

  facebookSdkPromise = new Promise((resolve, reject) => {
    const previousInit = window.fbAsyncInit

    window.fbAsyncInit = () => {
      if (typeof previousInit === 'function') previousInit()

      if (!window.FB) {
        reject(new Error('Facebook SDK không khởi tạo được.'))
        return
      }

      resolve(initializeFacebook(appId, graphVersion))
    }

    const existingScript = document.getElementById('facebook-jssdk')
    if (existingScript) return

    const script = document.createElement('script')
    script.id = 'facebook-jssdk'
    script.src = 'https://connect.facebook.net/vi_VN/sdk.js'
    script.async = true
    script.defer = true
    script.crossOrigin = 'anonymous'
    script.onerror = () => reject(new Error('Không thể tải Facebook SDK.'))
    document.head.appendChild(script)
  })

  return facebookSdkPromise
}

export const requestFacebookLogin = async (appId, graphVersion) => {
  const facebook = await loadFacebookSdk(appId, graphVersion)

  return new Promise((resolve, reject) => {
    facebook.login((response) => {
      const accessToken = response?.authResponse?.accessToken

      if (accessToken) {
        resolve(accessToken)
        return
      }

      reject(new Error('Bạn đã hủy hoặc chưa cấp quyền đăng nhập Facebook.'))
    }, {
      scope: 'public_profile,email',
      return_scopes: true,
    })
  })
}
